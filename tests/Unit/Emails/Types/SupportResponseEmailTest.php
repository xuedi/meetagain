<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\SupportResponseEmail;
use App\Entity\SupportRequest;
use App\Enum\ContactType;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class SupportResponseEmailTest extends TestCase
{
    public function testSendEnqueuesEmailToRequester(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $blocklist = $this->createStub(BlocklistCheckerInterface::class);
        $blocklist->method('isBlocked')->willReturn(false);

        $enqueued = null;
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue
            ->expects($this->once())
            ->method('enqueue')
            ->with(
                $this->anything(),
                $this->callback(static function (TemplatedEmail $email) use (&$enqueued): bool {
                    $enqueued = $email;
                    return true;
                }),
                EmailType::SupportResponse,
                $this->anything(),
            );

        $emailType = new SupportResponseEmail($blocklist, $queue, $config);

        // Act
        $emailType->send(['request' => $this->makeRequest(), 'response' => 'Here is your answer.']);

        // Assert
        static::assertInstanceOf(TemplatedEmail::class, $enqueued);
        static::assertSame('john@example.com', $enqueued->getTo()[0]->getAddress());
        $context = $enqueued->getContext();
        static::assertSame('John', $context['name']);
        static::assertSame('Help!', $context['originalMessage']);
        static::assertSame('Here is your answer.', $context['response']);
        static::assertArrayHasKey('createdAt', $context);
    }

    public function testSendSkipsWhenRecipientBlocklisted(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $blocklist = $this->createStub(BlocklistCheckerInterface::class);
        $blocklist->method('isBlocked')->willReturn(true);

        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $emailType = new SupportResponseEmail($blocklist, $queue, $config);

        // Act
        $emailType->send(['request' => $this->makeRequest(), 'response' => 'Here is your answer.']);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        // Act & Assert
        static::assertSame(EmailType::SupportResponse->value, $emailType->getIdentifier());
    }

    private function makeRequest(): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getContactType')->willReturn(ContactType::General);
        $request->method('getName')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));

        return $request;
    }
}
