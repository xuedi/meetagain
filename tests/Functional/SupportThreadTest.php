<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\EmailQueue;
use App\Entity\SupportMessage;
use App\Entity\SupportRequest;
use App\Enum\SupportAudience;
use App\Enum\EmailType;
use App\Enum\SupportChannel;
use App\Enum\SupportMessageAuthor;
use App\Enum\SupportRequestStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SupportThreadTest extends WebTestCase
{
    private const string UNKNOWN_TOKEN = 'deadbeef00000000000000000000000000000000000000000000000000000000';

    public function testThreadPageCarriesTheThreeHardeningHeaders(): void
    {
        // Arrange
        $client = static::createClient();
        $token = $this->createThread($client);

        // Act
        $client->request('GET', '/en/contact/request/' . $token);

        // Assert
        $this->assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;
        static::assertSame('noindex, nofollow', $headers->get('X-Robots-Tag'));
        static::assertSame('no-referrer', $headers->get('Referrer-Policy'));
        static::assertStringContainsString('private', (string) $headers->get('Cache-Control'));
        static::assertStringContainsString('no-store', (string) $headers->get('Cache-Control'));
    }

    public function testAnUnknownTokenAndARetiredThreadAreIndistinguishable(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $retiredToken = $this->createThread($client);
        $retired = $this->reload($em, $retiredToken);
        $retired->setStatus(SupportRequestStatus::Resolved);
        $retired->setResolvedAt(new DateTimeImmutable('-40 days'));
        $retired->setToken(null);
        $em->flush();

        // Act
        $client->request('GET', '/en/contact/request/' . self::UNKNOWN_TOKEN);
        $unknown = $client->getResponse();
        $client->request('GET', '/en/contact/request/' . $retiredToken);
        $retiredResponse = $client->getResponse();

        // Assert
        static::assertSame(404, $unknown->getStatusCode());
        static::assertSame(404, $retiredResponse->getStatusCode());
        static::assertStringNotContainsString('The original guest question', (string) $retiredResponse->getContent());
        static::assertStringNotContainsString('Guest Requester', (string) $retiredResponse->getContent());
    }

    public function testARequesterReplyIsStoredEscapedAndReopensTheRequest(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client, SupportRequestStatus::Replied);

        // Act
        $crawler = $client->request('GET', '/en/contact/request/' . $token);
        $client->submit($crawler
            ->selectButton('Send')
            ->form([
                'support_thread_reply[message]' => 'Still broken <script>alert(1)</script>',
            ]));

        // Assert
        $this->assertResponseRedirects();
        $request = $this->reload($em, $token);
        static::assertSame(SupportRequestStatus::Reopened, $request->getStatus());

        $messages = $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $request], ['id' => 'ASC']);
        static::assertCount(2, $messages);
        static::assertSame(SupportMessageAuthor::Requester, $messages[1]->getAuthor());
        static::assertStringContainsString('&lt;script&gt;', $messages[1]->getContent());
        static::assertStringNotContainsString('<script>', $messages[1]->getContent());
    }

    public function testAReplyWithoutACsrfTokenIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);

        // Act
        $client->request('POST', '/en/contact/request/' . $token . '/reply', [
            'support_thread_reply' => ['message' => 'No token here.'],
        ]);

        // Assert
        $this->assertResponseRedirects();
        static::assertCount(1, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $this->reload($em, $token)]));
    }

    public function testAReplyCarryingAnotherThreadsCsrfTokenIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $victimToken = $this->createThread($client);
        $otherToken = $this->createThread($client);
        $otherCsrf = $this->replyCsrf($client, $otherToken);

        // Act
        $this->postReply($client, $victimToken, $otherCsrf, 'Posted with a token minted for another thread.');

        // Assert
        static::assertCount(1, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $this->reload($em, $victimToken)]));
    }

    public function testAResolvedThreadRejectsFurtherRequesterMessages(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);
        $csrf = $this->replyCsrf($client, $token);

        $request = $this->reload($em, $token);
        $request->setStatus(SupportRequestStatus::Resolved);
        $em->flush();

        // Act
        $crawler = $client->request('GET', '/en/contact/request/' . $token);
        $this->postReply($client, $token, $csrf, 'Let me back in.');

        // Assert
        static::assertCount(0, $crawler->selectButton('Send'), 'A closed thread offers no reply box');
        static::assertCount(1, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $this->reload($em, $token)]));
    }

    public function testFiveConsecutiveRequesterMessagesBlockTheSixth(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);
        $csrf = $this->replyCsrf($client, $token);
        $request = $this->reload($em, $token);
        for ($i = 0; $i < 3; $i++) {
            $this->appendMessage($em, $request);
        }

        // Act
        $this->postReply($client, $token, $csrf, 'Fifth message in a row.');
        $this->postReply($client, $token, $csrf, 'Sixth message in a row.');

        // Assert
        static::assertCount(5, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $this->reload($em, $token)]));
    }

    public function testTheFiftiethMessageIsTheLastOneAccepted(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);
        $csrf = $this->replyCsrf($client, $token);
        $request = $this->reload($em, $token);
        for ($i = 0; $i < 48; $i++) {
            $this->appendMessage($em, $request, SupportMessageAuthor::Admin);
        }

        // Act
        $this->postReply($client, $token, $csrf, 'The fiftieth message.');
        $this->postReply($client, $token, $csrf, 'One message past the cap.');

        // Assert
        static::assertCount(50, $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $this->reload($em, $token)]));
    }

    public function testTheEmailOptInSendsANeutralConfirmMailAndTheLinkVerifiesTheAddress(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);

        // Act
        $crawler = $client->request('GET', '/en/contact/request/' . $token);
        $client->submit($crawler
            ->selectButton('Notify me')
            ->form([
                'support_optin_email' => 'opt-in@example.org',
            ]));

        // Assert
        $this->assertResponseRedirects();
        $request = $this->reload($em, $token);
        static::assertNotNull($request->getEmailVerifyToken());
        static::assertNull($request->getEmailVerifiedAt(), 'Asking for notifications does not by itself confirm the address');

        $queued = $em->getRepository(EmailQueue::class)->findBy([
            'recipient' => 'opt-in@example.org',
            'template' => EmailType::SupportEmailVerify,
        ]);
        static::assertCount(1, $queued);
        $payload = implode("\n", array_merge([(string) $queued[0]->getSubject(), (string) $queued[0]->getRenderedBody()], array_map(
            static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
            $queued[0]->getContext(),
        )));
        static::assertStringNotContainsString('Guest Requester', $payload, 'The confirm mail carries no requester-supplied name');
        static::assertStringNotContainsString('The original guest question', $payload, 'The confirm mail carries no requester-supplied message');
        static::assertStringNotContainsString($token, $payload, 'The confirm mail never carries the secret thread token');
    }

    public function testAnExpiredOrAlreadyUsedVerifyLinkIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = $this->createThread($client);
        $request = $this->reload($em, $token);
        $verifyToken = str_repeat('b', 64);
        $request->setEmail('opt-in@example.org');
        $request->setEmailVerifyToken($verifyToken);
        $request->setEmailVerifyExpiresAt(new DateTimeImmutable('+24 hours'));
        $em->flush();

        // Act
        $client->request('GET', '/en/contact/verify/' . $verifyToken);
        $firstStatus = $client->getResponse()->getStatusCode();
        $client->request('GET', '/en/contact/verify/' . $verifyToken);
        $secondStatus = $client->getResponse()->getStatusCode();

        // Assert
        static::assertSame(200, $firstStatus);
        static::assertSame(404, $secondStatus, 'The verify token is single-use');
        static::assertNotNull($this->reload($em, $token)->getEmailVerifiedAt());
    }

    private function createThread(KernelBrowser $client, SupportRequestStatus $status = SupportRequestStatus::New): string
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $token = bin2hex(random_bytes(32));

        $request = new SupportRequest();
        $request->setEmail(null);
        $request->setAudience(SupportAudience::Organizer);
        $request->setMessage('The original guest question');
        $request->setCreatedAt(new DateTimeImmutable('-1 day'));
        $request->setStatus($status);
        $request->setChannel(SupportChannel::Thread);
        $request->setToken($token);
        $request->setLastActivityAt(new DateTimeImmutable('-1 day'));
        $em->persist($request);

        $opening = new SupportMessage();
        $opening->setSupportRequest($request);
        $opening->setAuthor(SupportMessageAuthor::Requester);
        $opening->setContent('The original guest question');
        $opening->setCreatedAt(new DateTimeImmutable('-1 day'));
        $em->persist($opening);
        $em->flush();

        return $token;
    }

    private function appendMessage(EntityManagerInterface $em, SupportRequest $request, SupportMessageAuthor $author = SupportMessageAuthor::Requester): void
    {
        $message = new SupportMessage();
        $message->setSupportRequest($request);
        $message->setAuthor($author);
        $message->setContent('Filler message');
        $message->setCreatedAt(new DateTimeImmutable());
        $em->persist($message);
        $em->flush();
    }

    private function postReply(KernelBrowser $client, #[\SensitiveParameter] string $token, string $csrf, string $body): void
    {
        $client->request('POST', '/en/contact/request/' . $token . '/reply', [
            'support_thread_reply' => ['message' => $body, '_token' => $csrf],
        ]);
    }

    private function replyCsrf(KernelBrowser $client, #[\SensitiveParameter] string $token): string
    {
        $crawler = $client->request('GET', '/en/contact/request/' . $token);

        return (string) $crawler->filter('form[action$="/reply"] input[name="support_thread_reply[_token]"]')->attr('value');
    }

    private function reload(EntityManagerInterface $em, #[\SensitiveParameter] string $token): SupportRequest
    {
        $request = $em->getRepository(SupportRequest::class)->findOneBy(['token' => $token]);
        static::assertNotNull($request);

        return $request;
    }
}
