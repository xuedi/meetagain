<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\EmailQueue;
use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Service\ConfigService;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class EmailServiceTest extends TestCase
{
    public function testPrepareVerificationRequestEnqueuesEmailWithExpectedData(): void
    {
        // Arrange: create user and mock entity manager to verify email queue entity
        $user = $this->makeUser('user@example.com', 'Alice', 'en', 'abc123');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($entity) {
                    $this->assertInstanceOf(EmailQueue::class, $entity);
                    /* @var EmailQueue $entity */
                    $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                    $this->assertSame('user@example.com', $entity->getRecipient());
                    $this->assertSame('Please Confirm your Email', $entity->getSubject());
                    $this->assertSame('_emails/verification_request.html.twig', $entity->getTemplate());
                    $this->assertSame('en', $entity->getLang());

                    $ctx = $entity->getContext();
                    $this->assertSame('https://example.com', $ctx['host']);
                    $this->assertSame('abc123', $ctx['token']);
                    $this->assertSame('example.com', $ctx['url']);
                    $this->assertSame('Alice', $ctx['username']);
                    $this->assertSame('en', $ctx['lang']);

                    $this->assertNotNull($entity->getCreatedAt());
                    $this->assertNull($entity->getSendAt());

                    return true;
                })
            );
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act: prepare verification request
        $ok = $service->prepareVerificationRequest($user);

        // Assert: returns true
        $this->assertTrue($ok);
    }

    public function testPrepareWelcomeAndResetPasswordAlsoEnqueue(): void
    {
        // Arrange: mock entity manager to verify persist/flush calls
        $user = $this->makeUser('bob@example.com', 'Bob', 'de', 'reg-999');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->exactly(2))->method('persist');
        $emMock->expects($this->exactly(2))->method('flush');

        $service = $this->createService(em: $emMock);

        // Act & Assert: both methods enqueue emails successfully
        $this->assertTrue($service->prepareWelcome($user));
        $this->assertTrue($service->prepareResetPassword($user));
    }

    public function testSendQueueSendsPendingEmailsAndMarksAsSent(): void
    {
        // Arrange: create queued email
        $queued = (new EmailQueue())
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setTemplate('_emails/verification_request.html.twig')
            ->setLang('en')
            ->setContext(['k' => 'v']);

        // Arrange: mock mail repository to return pending email
        $mailRepoMock = $this->createMock(EmailQueueRepository::class);
        $mailRepoMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['sendAt' => null], ['id' => 'ASC'], 1000)
            ->willReturn([$queued]);

        // Arrange: mock mailer to verify send is called
        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(TemplatedEmail::class));

        // Arrange: mock entity manager to verify persist/flush
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')
            ->with(
                $this->callback(function ($entity) use ($queued) {
                    $this->assertSame($queued, $entity);
                    $this->assertInstanceOf(DateTime::class, $queued->getSendAt());

                    return true;
                })
            );
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(
            mailer: $mailerMock,
            mailRepo: $mailRepoMock,
            em: $emMock,
        );

        // Act: send queue
        $service->sendQueue();
    }

    public function testPrepareEventCanceledNotificationEnqueuesEmailWithExpectedData(): void
    {
        // Arrange: create user and event with translation
        $user = $this->makeUser('user@example.com', 'Alice', 'en');

        $translation = new \App\Entity\EventTranslation();
        $translation->setTitle('Test Event');
        $translation->setLanguage('en');

        $event = new \Tests\Unit\Stubs\EventStub();
        $event->setId(42);
        $event->addTranslation($translation);
        $event->setStart(new DateTimeImmutable('2025-06-15 14:00:00'));
        $locationStub = $this->createStub(\App\Entity\Location::class);
        $locationStub->method('getName')->willReturn('Test Venue');
        $event->setLocation($locationStub);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($entity) {
                    $this->assertInstanceOf(EmailQueue::class, $entity);
                    /* @var EmailQueue $entity */
                    $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                    $this->assertSame('user@example.com', $entity->getRecipient());
                    $this->assertSame('Event canceled: Test Event', $entity->getSubject());
                    $this->assertSame('_emails/notification_event_canceled.html.twig', $entity->getTemplate());
                    $this->assertSame('en', $entity->getLang());

                    $ctx = $entity->getContext();
                    $this->assertSame('Alice', $ctx['username']);
                    $this->assertSame('Test Venue', $ctx['eventLocation']);
                    $this->assertSame('2025-06-15', $ctx['eventDate']);
                    $this->assertSame(42, $ctx['eventId']);
                    $this->assertSame('Test Event', $ctx['eventTitle']);
                    $this->assertSame('https://example.com', $ctx['host']);
                    $this->assertSame('en', $ctx['lang']);

                    return true;
                })
            );
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act: prepare event canceled notification
        $ok = $service->prepareEventCanceledNotification($user, $event);

        // Assert: returns true
        $this->assertTrue($ok);
    }

    public function testPrepareMessageNotificationEnqueuesEmailWithExpectedData(): void
    {
        // Arrange: create sender and recipient users
        $sender = $this->makeUser('sender@example.com', 'Bob', 'en');
        $recipient = $this->makeUser('recipient@example.com', 'Alice', 'de');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($entity) {
                    $this->assertInstanceOf(EmailQueue::class, $entity);
                    /* @var EmailQueue $entity */
                    $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                    $this->assertSame('recipient@example.com', $entity->getRecipient());
                    $this->assertSame('You received a message from Bob', $entity->getSubject());
                    $this->assertSame('_emails/notification_message.html.twig', $entity->getTemplate());
                    $this->assertSame('de', $entity->getLang());

                    $ctx = $entity->getContext();
                    $this->assertSame('Alice', $ctx['username']);
                    $this->assertSame('Bob', $ctx['sender']);
                    $this->assertSame('https://example.com', $ctx['host']);
                    $this->assertSame('de', $ctx['lang']);

                    return true;
                })
            );
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act: prepare message notification
        $ok = $service->prepareMessageNotification($sender, $recipient);

        // Assert: returns true
        $this->assertTrue($ok);
    }

    public function testGetMockEmailListReturnsAllEmailTemplates(): void
    {
        // Arrange: create service
        $service = $this->createService();

        // Act: get mock email list
        $result = $service->getMockEmailList();

        // Assert: contains all expected email templates
        $this->assertArrayHasKey('email_message_notification', $result);
        $this->assertArrayHasKey('email_rsvp_notification_aggregated', $result);
        $this->assertArrayHasKey('email_welcome', $result);
        $this->assertArrayHasKey('email_verification_request', $result);
        $this->assertArrayHasKey('email_password_reset_request', $result);
        $this->assertArrayHasKey('email_event_canceled', $result);

        // Assert: each entry has expected structure
        foreach ($result as $emailData) {
            $this->assertArrayHasKey('subject', $emailData);
            $this->assertArrayHasKey('template', $emailData);
            $this->assertArrayHasKey('context', $emailData);
        }
    }

    private function createService(
        ?MailerInterface $mailer = null,
        ?ConfigService $config = null,
        ?EmailQueueRepository $mailRepo = null,
        ?EntityManagerInterface $em = null,
        ?EmailTemplateService $templateService = null,
    ): EmailService {
        if ($config === null) {
            $config = $this->createStub(ConfigService::class);
            $config->method('getMailerAddress')->willReturn(new Address('sender@email.com', 'email sender'));
            $config->method('getHost')->willReturn('https://example.com');
            $config->method('getUrl')->willReturn('example.com');
        }

        if ($templateService === null) {
            $templateService = $this->createStub(EmailTemplateService::class);
            $templateService->method('getTemplate')->willReturn(null);
        }

        return new EmailService(
            mailer: $mailer ?? $this->createStub(MailerInterface::class),
            config: $config,
            mailRepo: $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
        );
    }

    private function makeUser(
        string $email,
        string $name = 'Alice',
        string $locale = 'en',
        string $regcode = 'token-123',
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale($locale);
        $user->setRegcode($regcode);

        return $user;
    }
}
