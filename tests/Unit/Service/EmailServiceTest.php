<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\EmailContextEnricherInterface;
use App\Entity\EmailQueue;
use App\Entity\EventTranslation;
use App\Entity\SupportRequest;
use App\Enum\ContactType;
use App\Enum\EmailQueueStatus;
use App\Enum\EmailType;
use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use App\Service\Email\EmailTemplateService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Tests\Unit\Stubs\EventStub;

final class EmailServiceTest extends TestCase
{
    public function testPrepareVerificationRequestEnqueuesEmailWithExpectedData(): void
    {
        // Arrange: create user and mock entity manager to verify email queue entity
        $user = $this->makeUser('user@example.com', 'Alice', 'en', 'abc123');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) {
                $this->assertInstanceOf(EmailQueue::class, $entity);
                /* @var EmailQueue $entity */
                $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                $this->assertSame('user@example.com', $entity->getRecipient());
                $this->assertSame('Please Confirm your Email', $entity->getSubject());
                $this->assertSame('<p>Verification body</p>', $entity->getRenderedBody());
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
            }));
        $emMock->expects($this->once())->method('flush');

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService
            ->method('getTemplateContent')
            ->willReturn([
                'subject' => 'Please Confirm your Email',
                'body' => '<p>Verification body</p>',
            ]);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $content) => $content);

        $service = $this->createService(em: $emMock, templateService: $templateService);

        // Act: prepare verification request
        $ok = $service->prepareVerificationRequest($user);

        // Assert: returns true
        static::assertTrue($ok);
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
        static::assertTrue($service->prepareWelcome($user));
        static::assertTrue($service->prepareResetPassword($user));
    }

    public function testSendQueueSendsPendingEmailsAndMarksAsSent(): void
    {
        // Arrange: create queued email
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>Rendered email body</p>')
            ->setLang('en')
            ->setContext(['k' => 'v']);

        // Arrange: mock mail repository to return pending email
        $mailRepoMock = $this->createMock(EmailQueueRepository::class);
        $mailRepoMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => EmailQueueStatus::Pending], ['id' => 'ASC'], 1000)
            ->willReturn([$queued]);

        // Arrange: mock transport to verify send is called
        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('msg-id-123');
        $mailerMock = $this->createMock(TransportInterface::class);
        $mailerMock
            ->expects($this->once())
            ->method('send')
            ->with(static::isInstanceOf(TemplatedEmail::class))
            ->willReturn($sentMessage);

        // Arrange: mock entity manager to verify persist/flush
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) use ($queued) {
                $this->assertSame($queued, $entity);
                $this->assertInstanceOf(DateTime::class, $queued->getSendAt());
                $this->assertSame(EmailQueueStatus::Sent, $queued->getStatus());

                return true;
            }));
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoMock, em: $emMock);

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
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) {
                $this->assertInstanceOf(EmailQueue::class, $entity);
                /* @var EmailQueue $entity */
                $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                $this->assertSame('user@example.com', $entity->getRecipient());
                $this->assertSame('Event canceled: Test Event', $entity->getSubject());
                $this->assertSame('<p>Event canceled body</p>', $entity->getRenderedBody());
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
            }));
        $emMock->expects($this->once())->method('flush');

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService
            ->method('getTemplateContent')
            ->willReturn([
                'subject' => 'Event canceled: Test Event',
                'body' => '<p>Event canceled body</p>',
            ]);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $content) => $content);

        $service = $this->createService(em: $emMock, templateService: $templateService);

        // Act: prepare event canceled notification
        $ok = $service->prepareEventCanceledNotification($user, $event);

        // Assert: returns true
        static::assertTrue($ok);
    }

    public function testPrepareMessageNotificationEnqueuesEmailWithExpectedData(): void
    {
        // Arrange: create sender and recipient users
        $sender = $this->makeUser('sender@example.com', 'Bob', 'en');
        $recipient = $this->makeUser('recipient@example.com', 'Alice', 'de');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) {
                $this->assertInstanceOf(EmailQueue::class, $entity);
                /* @var EmailQueue $entity */
                $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                $this->assertSame('recipient@example.com', $entity->getRecipient());
                $this->assertSame('You received a message from Bob', $entity->getSubject());
                $this->assertSame('<p>Message notification body</p>', $entity->getRenderedBody());
                $this->assertSame('de', $entity->getLang());

                $ctx = $entity->getContext();
                $this->assertSame('Alice', $ctx['username']);
                $this->assertSame('Bob', $ctx['sender']);
                $this->assertSame('https://example.com', $ctx['host']);
                $this->assertSame('de', $ctx['lang']);

                return true;
            }));
        $emMock->expects($this->once())->method('flush');

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService
            ->method('getTemplateContent')
            ->willReturn([
                'subject' => 'You received a message from Bob',
                'body' => '<p>Message notification body</p>',
            ]);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $content) => $content);

        $service = $this->createService(em: $emMock, templateService: $templateService);

        // Act: prepare message notification
        $ok = $service->prepareMessageNotification($sender, $recipient);

        // Assert: returns true
        static::assertTrue($ok);
    }

    public function testGetMockEmailListReturnsAllEmailTemplates(): void
    {
        // Arrange: create service
        $service = $this->createService();

        // Act: get mock email list
        $result = $service->getMockEmailList();

        // Assert: contains all expected email templates (using EmailType enum values)
        static::assertArrayHasKey('notification_message', $result);
        static::assertArrayHasKey('notification_rsvp_aggregated', $result);
        static::assertArrayHasKey('welcome', $result);
        static::assertArrayHasKey('verification_request', $result);
        static::assertArrayHasKey('password_reset_request', $result);
        static::assertArrayHasKey('notification_event_canceled', $result);

        // Assert: each entry has expected structure
        foreach ($result as $emailData) {
            static::assertArrayHasKey('subject', $emailData);
            static::assertArrayHasKey('context', $emailData);
        }
    }

    // ---- prepareAggregatedRsvpNotification ----

    #[DataProvider('aggregatedRsvpProvider')]
    public function testPrepareAggregatedRsvpNotification(
        array $attendeeNames,
        string $expectedAttendeeNames,
    ): void {
        // Arrange
        $recipient = $this->makeUser('host@example.com', 'Host', 'de');
        $attendees = array_map(
            fn(string $name) => $this->makeUser($name . '@example.com', $name),
            $attendeeNames,
        );

        $translation = new EventTranslation();
        $translation->setLanguage('de');
        $translation->setTitle('Event Title');

        $event = new EventStub();
        $event->setId(5);
        $event->setStart(new DateTime('2025-07-10 18:00'));
        $event->addTranslation($translation);

        $capturedContext = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')
            ->with(static::callback(function (EmailQueue $q) use (&$capturedContext) {
                $capturedContext = $q->getContext();
                return true;
            }));

        $service = $this->createService(em: $emMock);

        // Act
        $result = $service->prepareAggregatedRsvpNotification($recipient, $attendees, $event);

        // Assert
        static::assertTrue($result);
        static::assertSame($expectedAttendeeNames, $capturedContext['attendeeNames']);
        static::assertSame('de', $capturedContext['lang']);
        static::assertSame('Host', $capturedContext['username']);
    }

    public static function aggregatedRsvpProvider(): iterable
    {
        yield 'single attendee — attendeeNames is their name'       => [['Alice'], 'Alice'];
        yield 'two attendees — attendeeNames is comma-separated'    => [['Alice', 'Bob'], 'Alice, Bob'];
        yield 'three attendees — attendeeNames lists all three'     => [['Alice', 'Bob', 'Carol'], 'Alice, Bob, Carol'];
    }

    // ---- prepareAnnouncementEmail ----

    public function testPrepareAnnouncementEmailFlushTrueCallsPersistAndFlush(): void
    {
        // Arrange
        $recipient = $this->makeUser('user@example.com', 'Alice', 'en');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act
        $result = $service->prepareAnnouncementEmail(
            recipient: $recipient,
            renderedContent: ['title' => 'Big News', 'content' => '<p>...</p>'],
            announcementUrl: 'https://example.com/announcement/1',
        );

        // Assert
        static::assertTrue($result);
    }

    public function testPrepareAnnouncementEmailFlushFalsePersistsButDoesNotFlush(): void
    {
        // Arrange
        $recipient = $this->makeUser('user@example.com', 'Alice', 'en');

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist');
        $emMock->expects($this->never())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act
        $result = $service->prepareAnnouncementEmail(
            recipient: $recipient,
            renderedContent: ['title' => 'Big News', 'content' => '<p>...</p>'],
            announcementUrl: 'https://example.com/announcement/1',
            flush: false,
        );

        // Assert
        static::assertTrue($result);
    }

    // ---- prepareAdminNotification ----

    public function testPrepareAdminNotificationEnqueuesWithCorrectContext(): void
    {
        // Arrange
        $recipient = $this->makeUser('admin@example.com', 'Admin', 'en');

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')
            ->with(static::callback(function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock);

        // Act
        $result = $service->prepareAdminNotification($recipient, '<ul><li>1 pending</li></ul>');

        // Assert
        static::assertTrue($result);
        $ctx = $capturedQueue->getContext();
        static::assertSame('Admin', $ctx['username']);
        static::assertSame('<ul><li>1 pending</li></ul>', $ctx['sections']);
        static::assertSame('https://example.com', $ctx['host']);
        static::assertSame('en', $ctx['lang']);
    }

    // ---- prepareSupportNotification ----

    public function testPrepareSupportNotificationEnqueuesWithCorrectContextAndSendsToAdmin(): void
    {
        // Arrange
        $request = new SupportRequest();
        $request->setName('Jane Doe');
        $request->setEmail('jane@example.com');
        $request->setMessage('Help me please');
        $request->setCreatedAt(new DateTimeImmutable('2025-08-01 10:00:00'));
        $request->setContactType(ContactType::Bug);

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')
            ->with(static::callback(function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock);

        // Act
        $result = $service->prepareSupportNotification($request);

        // Assert
        static::assertTrue($result);
        // Recipient is the admin mailer address (sends to itself)
        static::assertSame('"email sender" <sender@email.com>', $capturedQueue->getRecipient());
        $ctx = $capturedQueue->getContext();
        static::assertSame('Report a bug', $ctx['contactType']);
        static::assertSame('Jane Doe', $ctx['name']);
        static::assertSame('jane@example.com', $ctx['email']);
        static::assertSame('Help me please', $ctx['message']);
        static::assertSame('2025-08-01 10:00:00', $ctx['createdAt']);
    }

    // ---- sendQueue() error path ----

    public function testSendQueueTransportExceptionSetsFailedStatusAndReturnsFailedCount(): void
    {
        // Arrange
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Fail test')
            ->setRenderedBody('<p>Body</p>')
            ->setLang('en')
            ->setContext([]);

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$queued]);

        $exception = new class('Connection refused') extends \RuntimeException implements TransportExceptionInterface {
            public function getDebug(): string { return ''; }
            public function appendDebug(string $debug): void {}
        };

        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub->method('send')->willThrowException($exception);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning');

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame('0 (Failed: 1)', $result);
        static::assertSame(EmailQueueStatus::Failed, $queued->getStatus());
        static::assertSame('Connection refused', $queued->getErrorMessage());
    }

    public function testSendQueueMixedResultReturnsCorrectCountAndLogsWarning(): void
    {
        // Arrange: 1 good email, 1 failing email
        $good = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('good@example.com')
            ->setSubject('Ok')
            ->setRenderedBody('<p>Ok</p>')
            ->setLang('en')
            ->setContext([]);

        $bad = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('bad@example.com')
            ->setSubject('Fail')
            ->setRenderedBody('<p>Fail</p>')
            ->setLang('en')
            ->setContext([]);

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$good, $bad]);

        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('ok-id');

        $exception = new class('Timeout') extends \RuntimeException implements TransportExceptionInterface {
            public function getDebug(): string { return ''; }
            public function appendDebug(string $debug): void {}
        };

        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub->method('send')->willReturnOnConsecutiveCalls($sentMessage, $this->throwException($exception));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning')
            ->with('Email queue processed with failures', ['sent' => 1, 'failed' => 1]);

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame('1 (Failed: 1)', $result);
    }

    // ---- runCronTask ----

    public function testRunCronTaskWritesQueueCountToOutput(): void
    {
        // Arrange: empty queue → count = '0'
        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([]);

        $outputMock = $this->createMock(OutputInterface::class);
        $outputMock->expects($this->once())->method('writeln')->with('EmailService: 0');

        $service = $this->createService(mailRepo: $mailRepoStub);

        // Act
        $service->runCronTask($outputMock);
    }

    // ---- addToEmailQueue with enrichers ----

    public function testEnricherContextKeyAppearsInPersistedEmailQueue(): void
    {
        // Arrange: enricher that injects a custom key
        $enricher = new class implements EmailContextEnricherInterface {
            public function enrich(array $context, string $locale): array
            {
                $context['custom_key'] = 'enriched_value';
                return $context;
            }
        };

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')
            ->with(static::callback(function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock, enrichers: [$enricher]);

        // Act
        $service->prepareWelcome($this->makeUser('user@example.com', 'Alice', 'en'));

        // Assert
        static::assertSame('enriched_value', $capturedQueue->getContext()['custom_key']);
    }

    private function createService(
        ?TransportInterface $mailer = null,
        ?ConfigService $config = null,
        ?EmailQueueRepository $mailRepo = null,
        ?EntityManagerInterface $em = null,
        ?EmailTemplateService $templateService = null,
        ?LoggerInterface $logger = null,
        iterable $enrichers = [],
    ): EmailService {
        if ($config === null) {
            $config = $this->createStub(ConfigService::class);
            $config->method('getMailerAddress')->willReturn(new Address('sender@email.com', 'email sender'));
            $config->method('getHost')->willReturn('https://example.com');
            $config->method('getUrl')->willReturn('example.com');
        }

        if ($templateService === null) {
            $templateService = $this->createStub(EmailTemplateService::class);
            $templateService
                ->method('getTemplateContent')
                ->willReturn([
                    'subject' => 'Test Subject',
                    'body' => '<p>Test Body</p>',
                ]);
            $templateService
                ->method('renderContent')
                ->willReturnCallback(static fn(string $content, array $context) => $content);
        }

        return new EmailService(
            transport: $mailer ?? $this->createStub(TransportInterface::class),
            config: $config,
            mailRepo: $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            enrichers: $enrichers,
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
