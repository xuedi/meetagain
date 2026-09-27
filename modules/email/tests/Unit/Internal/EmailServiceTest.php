<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Module\Email\Contract\Attachment;
use Module\Email\Contract\ContextEnricherInterface;
use Module\Email\Contract\EmailInterface;
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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class EmailServiceTest extends TestCase
{
    private const string ATTACHMENT_PATH = '/tmp/email-service-test-attachment.pdf';

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

    public function testTheSourcesAttachmentsAreStoredOnTheQueuedRow(): void
    {
        // Arrange
        $source = $this->createStub(EmailInterface::class);
        $source->method('getIdentifier')->willReturn('invoice_receipt');
        $source->method('getAttachments')->willReturn([new Attachment(self::ATTACHMENT_PATH, 'INV-1.pdf')]);
        $stored = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (EmailQueue $row) use (&$stored): void {
            $stored = $row;
        });

        // Act
        $this->createService(em: $em)->enqueue($source, $this->plainEmail(), []);

        // Assert
        static::assertInstanceOf(EmailQueue::class, $stored);
        static::assertSame('invoice_receipt', $stored->getTemplate());
        static::assertSame(
            [['path' => self::ATTACHMENT_PATH, 'filename' => 'INV-1.pdf']],
            array_map(static fn(Attachment $attachment): array => $attachment->toArray(), $stored->getAttachments()),
        );
    }

    public function testAStoredAttachmentIsAttachedToTheDispatchedMessage(): void
    {
        // Arrange
        file_put_contents(self::ATTACHMENT_PATH, '%PDF-1.4 pretend');
        $sent = null;
        $transport = $this->capturingTransport($sent);

        // Act
        $this->createService(
            mailer: $transport,
            mailRepo: $this->repoWith($this->queuedRow([new Attachment(self::ATTACHMENT_PATH, 'INV-1.pdf')])),
        )->sendQueue();
        unlink(self::ATTACHMENT_PATH);

        // Assert
        static::assertInstanceOf(Email::class, $sent);
        static::assertCount(1, $sent->getAttachments());
        static::assertSame('INV-1.pdf', $sent->getAttachments()[0]->getFilename());
    }

    public function testAVanishedAttachmentIsLoggedAndTheMailStillGoesOut(): void
    {
        // Arrange
        $sent = null;
        $transport = $this->capturingTransport($sent);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Email attachment is missing and was skipped');

        // Act
        $this->createService(
            mailer: $transport,
            mailRepo: $this->repoWith($this->queuedRow([new Attachment('/tmp/does-not-exist.pdf', 'INV-1.pdf')])),
            logger: $logger,
        )->sendQueue();

        // Assert
        static::assertInstanceOf(Email::class, $sent);
        static::assertSame([], $sent->getAttachments());
    }

    /** @param list<Attachment> $attachments */
    private function queuedRow(array $attachments): EmailQueue
    {
        return new EmailQueue()
            ->setSender('sender@example.com')
            ->setRecipient('user@example.com')
            ->setSubject('Your invoice')
            ->setLang('en')
            ->setRenderedBody('<p>Thanks</p>')
            ->setStatus(QueueStatus::Pending)
            ->setAttachments($attachments);
    }

    private function repoWith(EmailQueue $row): EmailQueueRepository
    {
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('findBy')->willReturn([$row]);

        return $repo;
    }

    private function capturingTransport(?Email &$sent): TransportInterface
    {
        $transport = $this->createStub(TransportInterface::class);
        $transport
            ->method('send')
            ->willReturnCallback(function (Email $message) use (&$sent): SentMessage {
                $sent = $message;

                return $this->createStub(SentMessage::class);
            });

        return $transport;
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
