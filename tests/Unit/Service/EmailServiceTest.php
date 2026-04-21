<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\EmailContextEnricherInterface;
use App\Emails\EmailInterface;
use App\Entity\EmailQueue;
use App\Entity\User;
use App\Enum\EmailQueueStatus;
use App\Enum\EmailType;
use App\Repository\EmailQueueRepository;
use App\Service\Email\EmailService;
use App\Service\Email\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;

final class EmailServiceTest extends TestCase
{
    public function testEnqueuePersistsAndFlushesEmailQueueEntity(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setLocale('en');

        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'email sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context(['token' => 'abc123', 'username' => 'Alice']);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) {
                $this->assertInstanceOf(EmailQueue::class, $entity);
                $this->assertSame('"email sender" <sender@email.com>', $entity->getSender());
                $this->assertSame('user@example.com', $entity->getRecipient());
                $this->assertSame('en', $entity->getLang());
                $this->assertNotNull($entity->getCreatedAt());
                $this->assertNull($entity->getProviderDispatchedAt());
                $this->assertNull($entity->getMaxSendBy());

                return true;
            }));
        $emMock->expects($this->once())->method('flush');

        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService
            ->method('getTemplateContent')
            ->willReturn([
                'subject' => 'Test Subject',
                'body' => '<p>Test Body</p>',
            ]);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $c) => $c);

        $service = $this->createService(em: $emMock, templateService: $templateService);

        // Act
        $ok = $service->enqueue($this->nullCapSource(), $email, EmailType::VerificationRequest, []);

        // Assert
        static::assertTrue($ok);
    }

    public function testEnqueueCapturesMaxSendByFromSource(): void
    {
        // Arrange
        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        $cutoff = new DateTimeImmutable('2026-06-01 12:00:00');
        $source = $this->createStub(EmailInterface::class);
        $source->method('getMaxSendBy')->willReturn($cutoff);

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock);

        // Act
        $service->enqueue($source, $email, EmailType::EventReminder, ['event' => 'stub']);

        // Assert
        static::assertSame($cutoff, $capturedQueue->getMaxSendBy());
    }

    public function testEnqueueWithFlushFalsePersistsButDoesNotFlush(): void
    {
        // Arrange
        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist');
        $emMock->expects($this->never())->method('flush');

        $service = $this->createService(em: $emMock);

        // Act
        $service->enqueue($this->nullCapSource(), $email, EmailType::Announcement, [], false);
    }

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
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock, enrichers: [$enricher]);

        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        // Act
        $service->enqueue($this->nullCapSource(), $email, EmailType::Welcome, []);

        // Assert
        static::assertSame('enriched_value', $capturedQueue->getContext()['custom_key']);
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
                $this->assertInstanceOf(DateTimeImmutable::class, $queued->getProviderDispatchedAt());
                $this->assertSame(EmailQueueStatus::Sent, $queued->getStatus());

                return true;
            }));
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoMock, em: $emMock);

        // Act: send queue
        $service->sendQueue();
    }

    public function testSendQueueSkipsRowPastMaxSendByAndMarksLate(): void
    {
        // Arrange: a pending row whose cutoff is in the past
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>body</p>')
            ->setLang('en')
            ->setContext([])
            ->setMaxSendBy(new DateTimeImmutable('-1 hour'));

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$queued]);

        // Transport MUST NOT be called
        $mailerMock = $this->createMock(TransportInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error')
            ->with('Email dispatch skipped: past max_send_by cutoff', static::anything());
        $loggerMock->expects($this->once())->method('warning');

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame(EmailQueueStatus::Late, $queued->getStatus());
        static::assertNotNull($queued->getErrorMessage());
        static::assertStringContainsString('Dispatch cutoff passed', $queued->getErrorMessage());
        static::assertSame('0 (Late: 1)', $result);
    }

    public function testSendQueueDispatchesWhenMaxSendByInFuture(): void
    {
        // Arrange: cutoff is ahead, dispatch proceeds normally
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>body</p>')
            ->setLang('en')
            ->setContext([])
            ->setMaxSendBy(new DateTimeImmutable('+1 hour'));

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$queued]);

        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('id');

        $mailerMock = $this->createMock(TransportInterface::class);
        $mailerMock->expects($this->once())->method('send')->willReturn($sentMessage);

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoStub);

        // Act
        $service->sendQueue();

        // Assert
        static::assertSame(EmailQueueStatus::Sent, $queued->getStatus());
        static::assertInstanceOf(DateTimeImmutable::class, $queued->getProviderDispatchedAt());
    }

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
            public function getDebug(): string
            {
                return '';
            }

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
            public function getDebug(): string
            {
                return '';
            }

            public function appendDebug(string $debug): void {}
        };

        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub->method('send')->willReturnOnConsecutiveCalls($sentMessage, $this->throwException($exception));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with('Email queue processed with issues', ['sent' => 1, 'failed' => 1, 'late' => 0]);

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame('1 (Failed: 1)', $result);
    }

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

    private function nullCapSource(): EmailInterface
    {
        $source = $this->createStub(EmailInterface::class);
        $source->method('getMaxSendBy')->willReturn(null);
        return $source;
    }

    private function createService(
        ?TransportInterface $mailer = null,
        ?EmailQueueRepository $mailRepo = null,
        ?EntityManagerInterface $em = null,
        ?EmailTemplateService $templateService = null,
        ?LoggerInterface $logger = null,
        iterable $enrichers = [],
    ): EmailService {
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
            mailRepo: $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            enrichers: $enrichers,
        );
    }
}
