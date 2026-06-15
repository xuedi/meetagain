<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\EmailQueue;
use App\Entity\Message;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\ContactType;
use App\Enum\EmailType;
use App\Enum\SupportReplyChannel;
use App\Enum\SupportRequestStatus;
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

    public function testGuestRequestShowsEmailReplyFormAndSends(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, self::GUEST_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $form = $crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Thanks for reaching out, here is your answer.',
        ]);
        static::assertStringContainsString('/reply-email', (string) $form->getUri());
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::Replied, $reloaded->getStatus());
        static::assertSame(SupportReplyChannel::Email, $reloaded->getReplyChannel());
        static::assertSame('Thanks for reaching out, here is your answer.', $reloaded->getResponse());

        $queued = $em->getRepository(EmailQueue::class)->findBy([
            'recipient' => self::GUEST_EMAIL,
            'template' => EmailType::SupportResponse,
        ]);
        static::assertNotEmpty($queued, 'A support_response email should be queued for the guest');

        // Reset
        $this->deleteQueuedFor($em, self::GUEST_EMAIL);
        $this->deleteRequest($em, $id);
    }

    public function testRepliedRequestLocksTheReplyForm(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, self::GUEST_EMAIL);

        // Act - reply once, then revisit the detail page
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $form = $crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'First and only answer.',
        ]);
        $client->submit($form);
        $crawler = $client->request('GET', '/en/admin/support/' . $id);

        // Assert - the stored response is shown in the locked view and no reply form remains
        static::assertStringContainsString('First and only answer.', $crawler->html());
        static::assertStringContainsString('Response sent', $crawler->html());
        static::assertCount(0, $crawler->selectButton('Send response'));
        static::assertStringNotContainsString('/reply-email', $crawler->html());

        // Reset
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $this->deleteQueuedFor($em, self::GUEST_EMAIL);
        $this->deleteRequest($em, $id);
    }

    public function testMemberRequestShowsMessageReplyFormAndSends(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, self::MEMBER_EMAIL);
        $member = $this->getUserByEmail($client, self::MEMBER_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/support/' . $id);
        $form = $crawler->selectButton('Send response')->form([
            'support_reply[response]' => 'Here is the answer to your member question.',
        ]);
        static::assertStringContainsString('/reply-message', (string) $form->getUri());
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::Replied, $reloaded->getStatus());
        static::assertSame(SupportReplyChannel::Message, $reloaded->getReplyChannel());
        static::assertSame('Here is the answer to your member question.', $reloaded->getResponse());

        $admin = $this->getUserByEmail($client, self::ADMIN_EMAIL);
        static::assertSame($admin->getId(), $reloaded->getRespondedBy()?->getId(), 'The responding admin owns the thread');

        $question = $em->getRepository(Message::class)->findOneBy(
            ['sender' => $member->getId(), 'receiver' => $admin->getId()],
            ['id' => 'DESC'],
        );
        static::assertNotNull($question, 'The user question should be imported as a message to the admin');
        static::assertStringStartsWith(Message::SUPPORT_QUESTION_MARKER, (string) $question->getContent());
        static::assertStringContainsString('Original question text', (string) $question->getContent());

        $answer = $em->getRepository(Message::class)->findOneBy(
            ['sender' => $admin->getId(), 'receiver' => $member->getId()],
            ['id' => 'DESC'],
        );
        static::assertNotNull($answer, 'The admin answer should be sent to the member');
        static::assertSame('Here is the answer to your member question.', (string) $answer->getContent());

        // Reset
        $em->remove($question);
        $em->remove($answer);
        $em->flush();
        $this->deleteRequest($em, $id);
    }

    public function testReplyWithoutCsrfTokenDoesNotSend(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $id = $this->createRequest($client, self::GUEST_EMAIL);

        // Act
        $client->request('POST', '/en/admin/support/' . $id . '/reply-email', [
            'support_reply' => ['response' => 'No token here.'],
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(SupportRequest::class)->find($id);
        static::assertSame(SupportRequestStatus::New, $reloaded->getStatus(), 'Status must not change without a valid CSRF token');
        static::assertEmpty(
            $em->getRepository(EmailQueue::class)->findBy(['recipient' => self::GUEST_EMAIL, 'template' => EmailType::SupportResponse]),
            'No email should be queued without a valid CSRF token',
        );

        // Reset
        $this->deleteRequest($em, $id);
    }

    private function createRequest(KernelBrowser $client, string $email): int
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $request = new SupportRequest();
        $request->setName('Test Requester');
        $request->setEmail($email);
        $request->setContactType(ContactType::General);
        $request->setMessage('Original question text');
        $request->setCreatedAt(new DateTimeImmutable());
        $request->setStatus(SupportRequestStatus::New);
        $em->persist($request);
        $em->flush();

        return (int) $request->getId();
    }

    private function deleteRequest(EntityManagerInterface $em, int $id): void
    {
        $request = $em->getRepository(SupportRequest::class)->find($id);
        if ($request instanceof SupportRequest) {
            $em->remove($request);
            $em->flush();
        }
    }

    private function deleteQueuedFor(EntityManagerInterface $em, string $email): void
    {
        foreach ($em->getRepository(EmailQueue::class)->findBy(['recipient' => $email, 'template' => EmailType::SupportResponse]) as $row) {
            $em->remove($row);
        }
        $em->flush();
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
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
