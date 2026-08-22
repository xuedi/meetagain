<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\SupportResponseEmail;
use App\Entity\SupportRequest;
use App\Enum\SupportAudience;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportResponseEmailTest extends TestCase
{
    use SampleFactoryTrait;

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
                $this->anything(),
            );

        $emailType = new SupportResponseEmail($blocklist, $this->mockSampleFactory(), $queue, $config);

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

        $emailType = new SupportResponseEmail($blocklist, $this->mockSampleFactory(), $queue, $config);

        // Act
        $emailType->send(['request' => $this->makeRequest(), 'response' => 'Here is your answer.']);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        // Act & Assert
        static::assertSame(EmailType::SupportResponse->value, $emailType->getIdentifier());
    }

    public function testSendSkipsWhenTheAddressWasNeverConfirmed(): void
    {
        // Arrange
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $blocklist = $this->createStub(BlocklistCheckerInterface::class);
        $blocklist->method('isBlocked')->willReturn(false);

        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $emailType = new SupportResponseEmail($blocklist, $this->mockSampleFactory(), $queue, $config);

        // Act
        $emailType->send(['request' => $this->makeRequest(verified: false), 'response' => 'Here is your answer.']);
    }

    public function testGuardSkipsWhenTheAddressWasNeverConfirmed(): void
    {
        // Arrange
        $emailType = new SupportResponseEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
        );

        // Act & Assert
        static::assertFalse($emailType->guardCheck(['request' => $this->makeRequest(verified: false)]));
    }

    private function makeRequest(bool $verified = true): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getAudience')->willReturn(SupportAudience::Organizer);
        $request->method('getRequesterLabel')->willReturn('John');
        $request->method('getEmail')->willReturn('john@example.com');
        $request->method('getMessage')->willReturn('Help!');
        $request->method('getCreatedAt')->willReturn(new DateTimeImmutable('2026-01-01'));
        $request->method('isEmailVerified')->willReturn($verified);

        return $request;
    }
}
