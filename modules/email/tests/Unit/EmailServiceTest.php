<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Module\Email\Contract\ContextEnricherInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\PushDispatcherInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendingIdentity;
use Module\Email\Contract\SendingIdentityProviderInterface;
use Module\Email\Internal\EmailService;
use Module\Email\Internal\EmailTemplateService;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\LayoutRenderer;
use Module\Email\Internal\Repository\EmailQueueRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

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
            ->method('render')
            ->willReturn([
                'subject' => 'Test Subject',
                'body' => '<p>Test Body</p>',
            ]);

        $service = $this->createService(em: $emMock, templateService: $templateService);

        // Act
        $ok = $service->enqueue($this->nullCapSource(), $email, []);

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
        $service->enqueue($source, $email, ['event' => 'stub']);

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
        $service->enqueue($this->nullCapSource(), $email, [], false);
    }

    public function testEnqueueHandsTheRowToEveryPushDispatcher(): void
    {
        // Arrange
        $dispatcher = $this->createMock(PushDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('', 'user@example.com', null);
        $service = $this->createService(pushDispatchers: [$dispatcher]);

        // Act
        $service->enqueue($this->nullCapSource(), $this->plainEmail(), []);
    }

    public function testEnqueueWithoutPushLeavesTheDispatchersAlone(): void
    {
        // Arrange
        $dispatcher = $this->createMock(PushDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $service = $this->createService(pushDispatchers: [$dispatcher]);

        // Act
        $service->enqueue($this->nullCapSource(), $this->plainEmail(), [], dispatchPush: false);
    }

    public function testAThrowingPushDispatcherDoesNotBreakTheEnqueue(): void
    {
        // Arrange
        $dispatcher = $this->createStub(PushDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('push down'));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = $this->createService(em: $em, pushDispatchers: [$dispatcher]);

        // Act
        $queued = $service->enqueue($this->nullCapSource(), $this->plainEmail(), []);

        // Assert
        static::assertTrue($queued);
    }

    public function testEnricherContextKeyAppearsInPersistedEmailQueue(): void
    {
        // Arrange
        $enricher = new class implements ContextEnricherInterface {
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
        $service->enqueue($this->nullCapSource(), $email, []);

        // Assert
        static::assertSame('enriched_value', $capturedQueue->getContext()['custom_key']);
    }

    public function testEnqueueDerivesHostAndUrlFromTheResolvedIdentity(): void
    {
        // Arrange
        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $layoutRenderer = $this->createStub(LayoutRenderer::class);
        $layoutRenderer->method('snapshot')->willReturn([]);

        $service = $this->createService(em: $emMock, layoutRenderer: $layoutRenderer, fallbackIdentity: self::identity(siteUrl: 'https://second.example'));

        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context(['host' => 'https://stale.example', 'url' => 'stale.example']);

        // Act
        $service->enqueue($this->nullCapSource(), $email, []);

        // Assert
        static::assertSame('https://second.example', $capturedQueue->getContext()['host']);
        static::assertSame('second.example', $capturedQueue->getContext()['url']);
    }

    public function testEnqueueSeedsTheSignOffFromTheIdentityAndLetsAnEnricherOverrideIt(): void
    {
        // Arrange
        $layoutRenderer = $this->createStub(LayoutRenderer::class);
        $layoutRenderer->method('snapshot')->willReturn([]);

        $seeded = null;
        $overridden = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$seeded) {
                $seeded = $q;
                return true;
            }));

        $service = $this->createService(em: $emMock, layoutRenderer: $layoutRenderer);
        $service->enqueue($this->nullCapSource(), $this->plainEmail(), []);

        $enricher = new class implements ContextEnricherInterface {
            public function enrich(array $context, string $locale): array
            {
                $context['greeting'] = 'Second Site';
                return $context;
            }
        };
        $emOverride = $this->createMock(EntityManagerInterface::class);
        $emOverride
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$overridden) {
                $overridden = $q;
                return true;
            }));

        // Act
        $this->createService(em: $emOverride, enrichers: [$enricher], layoutRenderer: $layoutRenderer)->enqueue(
            $this->nullCapSource(),
            $this->plainEmail(),
            [],
        );

        // Assert
        static::assertSame('Test Site', $seeded->getContext()['greeting']);
        static::assertSame('Second Site', $overridden->getContext()['greeting']);
    }

    public function testEnqueuePrefersTheFirstProviderThatClaimsTheOrigin(): void
    {
        // Arrange
        $origin = new User();

        $deferring = $this->createMock(SendingIdentityProviderInterface::class);
        $deferring->expects($this->once())->method('resolve')->with($origin, 'en')->willReturn(null);

        $claiming = $this->createMock(SendingIdentityProviderInterface::class);
        $claiming
            ->expects($this->once())
            ->method('resolve')
            ->with($origin, 'en')
            ->willReturn(self::identity(siteName: 'Second Site', siteUrl: 'https://second.example'));

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $layoutRenderer = $this->createStub(LayoutRenderer::class);
        $layoutRenderer->method('snapshot')->willReturnCallback(static fn(SendingIdentity $identity) => ['siteName' => $identity->siteName]);

        $service = $this->createService(em: $emMock, layoutRenderer: $layoutRenderer, identityProviders: [$deferring, $claiming]);

        // Act
        $service->enqueue($this->nullCapSource(), $this->plainEmail(), [], true, $origin);

        // Assert
        static::assertSame('https://second.example', $capturedQueue->getContext()['host']);
        static::assertSame('Second Site', $capturedQueue->getContext()['_layout']['siteName']);
    }

    public function testSendQueueSendsPendingEmailsAndMarksAsSent(): void
    {
        // Arrange
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>Rendered email body</p>')
            ->setLang('en')
            ->setContext(['k' => 'v']);

        // Arrange
        $mailRepoMock = $this->createMock(EmailQueueRepository::class);
        $mailRepoMock->expects($this->once())->method('findBy')->with(['status' => QueueStatus::Pending], ['id' => 'ASC'], 1000)->willReturn([$queued]);

        // Arrange
        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('msg-id-123');
        $mailerMock = $this->createMock(TransportInterface::class);
        $mailerMock->expects($this->once())->method('send')->with(static::isInstanceOf(TemplatedEmail::class))->willReturn($sentMessage);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(function ($entity) use ($queued) {
                $this->assertSame($queued, $entity);
                $this->assertInstanceOf(DateTimeImmutable::class, $queued->getProviderDispatchedAt());
                $this->assertSame(QueueStatus::Sent, $queued->getStatus());

                return true;
            }));
        $emMock->expects($this->once())->method('flush');

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoMock, em: $emMock);

        // Act
        $service->sendQueue();
    }

    public function testSendQueueSkipsRowPastMaxSendByAndMarksLate(): void
    {
        // Arrange
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

        $mailerMock = $this->createMock(TransportInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error')->with('Email dispatch skipped: past max_send_by cutoff', static::anything());
        $loggerMock->expects($this->once())->method('warning');

        $service = $this->createService(mailer: $mailerMock, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame(QueueStatus::Late, $queued->getStatus());
        static::assertNotNull($queued->getErrorMessage());
        static::assertStringContainsString('Dispatch cutoff passed', $queued->getErrorMessage());
        static::assertSame('0 (Late: 1)', $result);
    }

    public function testSendQueueDispatchesWhenMaxSendByInFuture(): void
    {
        // Arrange
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
        static::assertSame(QueueStatus::Sent, $queued->getStatus());
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

        $exception = new class('Connection refused') extends RuntimeException implements TransportExceptionInterface {
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
        static::assertSame(QueueStatus::Failed, $queued->getStatus());
        static::assertSame('Connection refused', $queued->getErrorMessage());
    }

    public function testSendQueueMixedResultReturnsCorrectCountAndLogsWarning(): void
    {
        // Arrange
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

        $exception = new class('Timeout') extends RuntimeException implements TransportExceptionInterface {
            public function getDebug(): string
            {
                return '';
            }

            public function appendDebug(string $debug): void {}
        };

        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub->method('send')->willReturnOnConsecutiveCalls($sentMessage, $this->throwException($exception));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning')->with('Email queue processed with issues', ['sent' => 1, 'failed' => 1, 'late' => 0]);

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, logger: $loggerMock);

        // Act
        $result = $service->sendQueue();

        // Assert
        static::assertSame('1 (Failed: 1)', $result);
    }

    public function testEnqueueStoresTheBareFragmentWithoutWrappingIt(): void
    {
        // Arrange
        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        $capturedQueue = null;
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static function (EmailQueue $q) use (&$capturedQueue) {
                $capturedQueue = $q;
                return true;
            }));

        $layoutRendererMock = $this->createMock(LayoutRenderer::class);
        $layoutRendererMock->expects($this->once())->method('snapshot')->willReturn(['siteName' => 'Example']);
        $layoutRendererMock->expects($this->never())->method('wrap');

        $service = $this->createService(em: $emMock, layoutRenderer: $layoutRendererMock);

        // Act
        $service->enqueue($this->nullCapSource(), $email, []);

        // Assert
        static::assertSame('<p>Test Body</p>', $capturedQueue->getRenderedBody());
        static::assertSame(['siteName' => 'Example'], $capturedQueue->getContext()[LayoutRenderer::CONTEXT_KEY]);
    }

    public function testEnqueueRefusesWhenNoIdentityProviderAnswers(): void
    {
        // Arrange
        $deferring = $this->createStub(SendingIdentityProviderInterface::class);
        $deferring->method('resolve')->willReturn(null);
        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService->method('render')->willReturn(['subject' => 'S', 'body' => 'B']);
        $service = new EmailService(
            transport: $this->createStub(TransportInterface::class),
            mailRepo: $this->createStub(EmailQueueRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
            layoutRenderer: $this->createStub(LayoutRenderer::class),
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
            identityProviders: [$deferring],
            pushDispatchers: [],
        );

        // Assert
        $this->expectException(LogicException::class);

        // Act
        $service->enqueue($this->nullCapSource(), $this->plainEmail(), []);
    }

    public function testARowQueuedBeforeTheLayoutExistedIsStillWrappedWhenItIsSent(): void
    {
        // Arrange
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>queued long ago</p>')
            ->setLang('en')
            ->setContext([]);

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$queued]);

        $captured = null;
        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('id');
        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$captured, $sentMessage) {
                $captured = $message;
                return $sentMessage;
            });

        $layoutRendererStub = $this->createStub(LayoutRenderer::class);
        $layoutRendererStub->method('wrap')->willReturn('<html><body><p>queued long ago</p></body></html>');

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, layoutRenderer: $layoutRendererStub);

        // Act
        $service->sendQueue();

        // Assert
        static::assertSame('<html><body><p>queued long ago</p></body></html>', $captured->getHtmlBody());
    }

    public function testTheLogoTravelsAsAUrlSoTheMessageCarriesNoPartsOfItsOwn(): void
    {
        // Arrange
        $queued = new EmailQueue()
            ->setSender('"email sender" <sender@email.com>')
            ->setRecipient('user@example.com')
            ->setSubject('Subject')
            ->setRenderedBody('<p>body</p>')
            ->setLang('en')
            ->setContext([]);

        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([$queued]);

        $captured = null;
        $sentMessage = $this->createStub(SentMessage::class);
        $sentMessage->method('getMessageId')->willReturn('id');
        $mailerStub = $this->createStub(TransportInterface::class);
        $mailerStub
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$captured, $sentMessage) {
                $captured = $message;
                return $sentMessage;
            });

        $layoutRendererStub = $this->createStub(LayoutRenderer::class);
        $layoutRendererStub->method('wrap')->willReturn('<html><body><img src="https://example.org/logo.png"></body></html>');

        $service = $this->createService(mailer: $mailerStub, mailRepo: $mailRepoStub, layoutRenderer: $layoutRendererStub);

        // Act
        $service->sendQueue();

        // Assert
        static::assertSame([], $captured->getAttachments());
        static::assertStringContainsString('src="https://example.org/logo.png"', (string) $captured->getHtmlBody());
    }

    public function testRunCronTaskWritesQueueCountToOutput(): void
    {
        // Arrange
        $mailRepoStub = $this->createStub(EmailQueueRepository::class);
        $mailRepoStub->method('findBy')->willReturn([]);

        $outputMock = $this->createMock(OutputInterface::class);
        $outputMock->expects($this->once())->method('writeln')->with('EmailService: 0');

        $service = $this->createService(mailRepo: $mailRepoStub);

        // Act
        $service->runCronTask($outputMock);
    }

    private function plainEmail(): TemplatedEmail
    {
        $email = new TemplatedEmail();
        $email->from(new Address('sender@email.com', 'Sender'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        return $email;
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
        ?LayoutRenderer $layoutRenderer = null,
        iterable $identityProviders = [],
        ?SendingIdentity $fallbackIdentity = null,
        iterable $pushDispatchers = [],
    ): EmailService {
        if ($templateService === null) {
            $templateService = $this->createStub(EmailTemplateService::class);
            $templateService
                ->method('render')
                ->willReturn([
                    'subject' => 'Test Subject',
                    'body' => '<p>Test Body</p>',
                ]);
        }

        if ($layoutRenderer === null) {
            $layoutRenderer = $this->createStub(LayoutRenderer::class);
            $layoutRenderer->method('snapshot')->willReturn([]);
            $layoutRenderer->method('wrap')->willReturnCallback(static fn(EmailQueue $mail) => '<html><body>' . $mail->getRenderedBody() . '</body></html>');
        }

        return new EmailService(
            transport: $mailer ?? $this->createStub(TransportInterface::class),
            mailRepo: $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
            layoutRenderer: $layoutRenderer,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            enrichers: $enrichers,
            identityProviders: [...$identityProviders, $this->answering($fallbackIdentity ?? self::identity())],
            pushDispatchers: $pushDispatchers,
        );
    }

    private function answering(SendingIdentity $identity): SendingIdentityProviderInterface
    {
        $provider = $this->createStub(SendingIdentityProviderInterface::class);
        $provider->method('resolve')->willReturn($identity);

        return $provider;
    }

    private static function identity(
        string $siteName = 'Test Site',
        string $siteUrl = 'https://test.example.com',
        string $greeting = 'Test Site',
    ): SendingIdentity {
        return new SendingIdentity(siteName: $siteName, siteUrl: $siteUrl, greeting: $greeting);
    }
}
