<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Emails\Types\SupportInvitationEmail;
use App\Entity\EmailQueue;
use App\Entity\Message;
use App\Entity\SupportMessage;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Enum\EmailType;
use App\Enum\SupportChannel;
use App\Enum\SupportMessageAuthor;
use App\Enum\SupportRequestStatus;
use App\Service\Support\ThreadService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SupportRequestReplyTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';
    private const string GUEST_EMAIL = 'guest-no-account@example.org';

    public function testAnswerToAGuestThreadLandsInTheThreadAndSendsNoMailToAnUnconfirmedAddress(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $client->submit($crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Thanks for reaching out, here is your answer.',
        ]));

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::Replied, $reloaded->getStatus());

        $messages = $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $reloaded], ['id' => 'ASC']);
        static::assertCount(2, $messages);
        static::assertSame(SupportMessageAuthor::Admin, $messages[1]->getAuthor());
        static::assertSame('Thanks for reaching out, here is your answer.', $messages[1]->getContent());

        static::assertEmpty(
            $em->getRepository(EmailQueue::class)->findBy(['recipient' => self::GUEST_EMAIL, 'template' => EmailType::SupportResponse]),
            'An unconfirmed guest address must never receive mail',
        );
    }

    public function testAnswerToAConfirmedGuestAddressAlsoQueuesTheNotification(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL, verified: true);

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $client->submit($crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Here is the answer for a confirmed address.',
        ]));

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        static::assertNotEmpty(
            $em->getRepository(EmailQueue::class)->findBy(['recipient' => self::GUEST_EMAIL, 'template' => EmailType::SupportResponse]),
            'A confirmed address gets the answer notification',
        );
    }

    public function testASecondAnswerIsAppendedRatherThanRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);

        // Act
        foreach (['First answer.', 'Second answer.'] as $body) {
            $crawler = $client->request('GET', '/en/admin/support/' . $id);
            $client->submit($crawler->selectButton('Send response')->form(['support_reply[response]' => $body]));
        }

        // Assert
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        $messages = $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $reloaded], ['id' => 'ASC']);
        static::assertCount(3, $messages);
        static::assertSame('Second answer.', $messages[2]->getContent());
    }

    public function testMemberRequestMirrorsTheAnswerIntoTheInbox(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Message, self::MEMBER_EMAIL);
        $member = $this->getUserByEmail($client, self::MEMBER_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $client->submit($crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Here is the answer to your member question.',
        ]));

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::Replied, $reloaded->getStatus());

        $admin = $this->getUserByEmail($client, self::ADMIN_EMAIL);
        static::assertSame($admin->getId(), $reloaded->getRespondedBy()?->getId(), 'The responding admin owns the thread');

        $question = $em->getRepository(Message::class)->findOneBy(['sender' => $member->getId(), 'receiver' => $admin->getId()], ['id' => 'DESC']);
        static::assertNotNull($question, 'The user question should be imported as a message to the admin');
        static::assertStringStartsWith(Message::SUPPORT_QUESTION_MARKER, (string) $question->getContent());
        static::assertStringContainsString('Original question text', (string) $question->getContent());

        $answer = $em->getRepository(Message::class)->findOneBy(['sender' => $admin->getId(), 'receiver' => $member->getId()], ['id' => 'DESC']);
        static::assertNotNull($answer, 'The admin answer should be sent to the member');
        static::assertSame('Here is the answer to your member question.', (string) $answer->getContent());
    }

    public function testMarkingResolvedClosesTheThreadAndReopeningLetsTheRequesterBack(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Act
        $this->clickTopAction($client, '/en/admin/support/' . $id, '/resolve');
        $em->clear();
        $resolved = $em->getRepository(SupportRequest::class)->find($id);
        $resolvedAt = $resolved->getResolvedAt();

        $this->clickTopAction($client, '/en/admin/support/' . $id, '/reopen');
        $em->clear();
        $reopened = $em->getRepository(SupportRequest::class)->find($id);

        // Assert
        static::assertNotNull($resolvedAt);
        static::assertFalse($resolved->isOpenForRequester());
        static::assertSame(SupportRequestStatus::Reopened, $reopened->getStatus());
        static::assertNull($reopened->getResolvedAt());
        static::assertTrue($reopened->isOpenForRequester());
    }

    public function testReplyWithoutCsrfTokenDoesNotSend(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL, verified: true);

        // Act
        $client->request('POST', '/en/admin/support/' . $id . '/reply', [
            'support_reply' => ['response' => 'No token here.'],
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::New, $reloaded->getStatus(), 'Status must not change without a valid CSRF token');
        static::assertCount(1, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $reloaded]));
        static::assertEmpty(
            $em->getRepository(EmailQueue::class)->findBy(['recipient' => self::GUEST_EMAIL, 'template' => EmailType::SupportResponse]),
            'No email should be queued without a valid CSRF token',
        );
    }

    public function testTheDetailPageLinksOutToTheRequestersOwnThreadPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $threadId = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);
        $memberId = $this->createRequest($client, SupportChannel::Message, self::MEMBER_EMAIL);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $em->getRepository(SupportRequest::class)->find($threadId)->getToken();

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $threadId);

        // Assert
        $this->assertResponseIsSuccessful();
        $link = $crawler->filter('a[href$="/contact/request/' . $token . '"]');
        static::assertCount(1, $link, 'The detail page links out to the requester thread');
        static::assertSame('_blank', $link->attr('target'), 'The thread opens in a new tab');
        static::assertSame('noopener', $link->attr('rel'));

        $memberCrawler = $client->request('GET', '/en/admin/support/' . $memberId);
        static::assertCount(
            0,
            $memberCrawler->filter('a[href*="/contact/request/"]'),
            'A member request has no thread page to link to',
        );
    }

    public function testInvitingTheAdminsNotifiesThemAndLeavesTheRequestWhereItWas(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $request = $em->getRepository(SupportRequest::class)->find($id);
        $steward = $this->getUserByEmail($client, self::MEMBER_EMAIL);

        // Act
        $client->getContainer()->get(ThreadService::class)->inviteAdmins($request, $steward);
        $client->getContainer()->get(SupportInvitationEmail::class)->send(['request' => $request]);

        // Assert
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportAudience::Organizer, $reloaded->getAudience(), 'An invitation must not move the request out of the steward view');
        static::assertTrue($reloaded->hasInvitedAdmins());
        static::assertSame($steward->getId(), $reloaded->getInvitedAdminsBy()?->getId());
        static::assertNotEmpty(
            $em->getRepository(EmailQueue::class)->findBy(['recipient' => self::ADMIN_EMAIL, 'template' => EmailType::SupportInvitation]),
            'The administrators must be notified about the invitation',
        );
    }

    public function testTheInvitedRequestStaysReachableAndAnswerableAfterwards(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, SupportChannel::Thread, self::GUEST_EMAIL);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $request = $em->getRepository(SupportRequest::class)->find($id);
        $client->getContainer()->get(ThreadService::class)->inviteAdmins($request, $this->getUserByEmail($client, self::MEMBER_EMAIL));

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);

        // Assert
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Answering after the admins joined.',
        ]));
        $this->assertResponseRedirects();
        $em->clear();
        static::assertSame(SupportRequestStatus::Replied, $em->getRepository(SupportRequest::class)->find($id)->getStatus());
    }

    private function createRequest(KernelBrowser $client, SupportChannel $channel, string $email, bool $verified = false, SupportAudience $audience = SupportAudience::Organizer): int
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $request = new SupportRequest();
        if ($channel === SupportChannel::Message) {
            $request->setRequester($this->getUserByEmail($client, $email));
        }
        $request->setEmail($channel === SupportChannel::Message ? null : $email);
        $request->setAudience($audience);
        $request->setMessage('Original question text');
        $request->setCreatedAt(new DateTimeImmutable());
        $request->setStatus(SupportRequestStatus::New);
        $request->setChannel($channel);
        $request->setLastActivityAt(new DateTimeImmutable());
        if ($channel === SupportChannel::Thread) {
            $request->setToken(bin2hex(random_bytes(32)));
        }
        if ($verified) {
            $request->setEmailVerifiedAt(new DateTimeImmutable());
        }
        $em->persist($request);

        $opening = new SupportMessage();
        $opening->setSupportRequest($request);
        $opening->setAuthor(SupportMessageAuthor::Requester);
        $opening->setContent('Original question text');
        $opening->setCreatedAt(new DateTimeImmutable());
        $em->persist($opening);
        $em->flush();

        return (int) $request->getId();
    }

    private function clickTopAction(KernelBrowser $client, string $page, string $hrefSuffix): void
    {
        $link = $client->request('GET', $page)->filter('a[href$="' . $hrefSuffix . '"][data-post]');
        static::assertCount(1, $link, "No topbar action pointing at {$hrefSuffix}");

        $client->request('POST', (string) $link->attr('href'), ['_token' => (string) $link->attr('data-csrf-token')]);
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function getUserByEmail(KernelBrowser $client, string $email): User
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        static::assertNotNull($user, "User {$email} should exist");

        return $user;
    }
}
