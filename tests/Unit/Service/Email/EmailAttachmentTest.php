<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Emails\Attachment;
use App\Emails\EmailInterface;
use App\Emails\SendingIdentity;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Repository\EmailQueueRepository;
use App\Service\Email\EmailService;
use App\Service\Email\EmailTemplateService;
use App\Service\Email\LayoutRenderer;
use App\Service\Email\RenderedLayout;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class EmailAttachmentTest extends TestCase
{
    private const string FIXTURE_PATH = '/tmp/email-attachment-test.pdf';

    protected function setUp(): void
    {
        file_put_contents(self::FIXTURE_PATH, '%PDF-1.4 pretend');
    }

    protected function tearDown(): void
    {
        if (file_exists(self::FIXTURE_PATH)) {
            unlink(self::FIXTURE_PATH);
        }
    }

    public function testTheSourcesAttachmentsAreStoredOnTheQueuedRow(): void
    {
        // Arrange
        $source = $this->createStub(EmailInterface::class);
        $source->method('getIdentifier')->willReturn('invoice_receipt');
        $source->method('getMaxSendBy')->willReturn(null);
        $source->method('getAttachments')->willReturn([new Attachment(self::FIXTURE_PATH, 'INV-1.pdf')]);

        $stored = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->willReturnCallback(static function (EmailQueue $row) use (&$stored): void {
            $stored = $row;
        });

        // Act
        $this->makeService(em: $em)->enqueue($source, $this->makeEmail(), []);

        // Assert
        $this->assertInstanceOf(EmailQueue::class, $stored);
        $this->assertSame('invoice_receipt', $stored->getTemplate());
        $this->assertCount(1, $stored->getAttachments());
        $this->assertSame('INV-1.pdf', $stored->getAttachments()[0]->filename);
    }

    public function testAStoredAttachmentIsAttachedToTheDispatchedMessage(): void
    {
        // Arrange
        $row = $this->makeQueuedRow([new Attachment(self::FIXTURE_PATH, 'INV-1.pdf')]);
        $sent = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(function (Email $message) use (&$sent): SentMessage {
            $sent = $message;
            return $this->createStub(SentMessage::class);
        });

        // Act
        $this->makeService(transport: $transport, mailRepo: $this->makeRepo($row))->sendQueue();

        // Assert
        $this->assertInstanceOf(Email::class, $sent);
        $this->assertCount(1, $sent->getAttachments());
        $this->assertSame('INV-1.pdf', $sent->getAttachments()[0]->getFilename());
    }

    public function testAVanishedAttachmentIsLoggedAndTheMailStillGoesOut(): void
    {
        // Arrange
        $row = $this->makeQueuedRow([new Attachment('/tmp/does-not-exist.pdf', 'INV-1.pdf')]);
        $sent = null;
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(function (Email $message) use (&$sent): SentMessage {
            $sent = $message;
            return $this->createStub(SentMessage::class);
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Email attachment is missing and was skipped');

        // Act
        $this->makeService(transport: $transport, mailRepo: $this->makeRepo($row), logger: $logger)->sendQueue();

        // Assert
        $this->assertInstanceOf(Email::class, $sent);
        $this->assertSame([], $sent->getAttachments());
    }

    /** @param list<Attachment> $attachments */
    private function makeQueuedRow(array $attachments): EmailQueue
    {
        $row = new EmailQueue();
        $row->setSender('sender@example.com');
        $row->setRecipient('user@example.com');
        $row->setSubject('Your invoice');
        $row->setLang('en');
        $row->setRenderedBody('<p>Thanks</p>');
        $row->setStatus(EmailQueueStatus::Pending);
        $row->setAttachments($attachments);

        return $row;
    }

    private function makeEmail(): TemplatedEmail
    {
        $email = new TemplatedEmail();
        $email->from(new Address('sender@example.com'));
        $email->to('user@example.com');
        $email->locale('en');
        $email->context([]);

        return $email;
    }

    private function makeRepo(EmailQueue $row): EmailQueueRepository
    {
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('findBy')->willReturn([$row]);

        return $repo;
    }

    private function makeService(
        ?TransportInterface $transport = null,
        ?EmailQueueRepository $mailRepo = null,
        ?EntityManagerInterface $em = null,
        ?LoggerInterface $logger = null,
    ): EmailService {
        $templateService = $this->createStub(EmailTemplateService::class);
        $templateService->method('getTemplateContent')->willReturn(['subject' => 'Your invoice', 'body' => '<p>Thanks</p>']);
        $templateService->method('renderContent')->willReturnCallback(static fn(string $content): string => $content);

        $layoutRenderer = $this->createStub(LayoutRenderer::class);
        $layoutRenderer->method('capture')->willReturn([]);
        $layoutRenderer->method('snapshot')->willReturn([]);
        $layoutRenderer
            ->method('captureIdentity')
            ->willReturn(new SendingIdentity(siteName: 'Test Site', siteUrl: 'https://test.example.com'));
        $layoutRenderer
            ->method('wrap')
            ->willReturnCallback(static fn(EmailQueue $mail) => new RenderedLayout('<html><body>' . $mail->getRenderedBody() . '</body></html>'));

        return new EmailService(
            transport: $transport ?? $this->createStub(TransportInterface::class),
            mailRepo: $mailRepo ?? $this->createStub(EmailQueueRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            templateService: $templateService,
            layoutRenderer: $layoutRenderer,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            enrichers: [],
            identityProviders: [],
        );
    }
}
