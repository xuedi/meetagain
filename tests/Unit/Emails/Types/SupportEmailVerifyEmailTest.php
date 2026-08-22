<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\EmailQueueInterface;
use App\Emails\Types\SupportEmailVerifyEmail;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Http\RequestHostResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;

class SupportEmailVerifyEmailTest extends TestCase
{
    use SampleFactoryTrait;

    private const string REQUESTER_NAME = 'Mallory Attacker';
    private const string REQUESTER_MESSAGE = 'Click here to claim your prize.';

    public function testContextCarriesNoRequesterSuppliedField(): void
    {
        // Arrange
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

        $emailType = $this->createEmailType($queue);

        // Act
        $emailType->send([
            'email' => 'victim@example.com',
            'token' => str_repeat('a', 64),
            'expiresAt' => new DateTimeImmutable('2026-01-02 12:00:00'),
            'lang' => 'en',
            'name' => self::REQUESTER_NAME,
            'message' => self::REQUESTER_MESSAGE,
        ]);

        // Assert
        static::assertInstanceOf(TemplatedEmail::class, $enqueued);
        static::assertSame(['lang', 'token', 'expiresAt'], array_keys($enqueued->getContext()));
        static::assertStringNotContainsString(self::REQUESTER_NAME, implode("\n", $enqueued->getContext()));
        static::assertStringNotContainsString(self::REQUESTER_MESSAGE, implode("\n", $enqueued->getContext()));
    }

    public function testSendSkipsWhenRecipientBlocklisted(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        $emailType = $this->createEmailType($queue, blocked: true);

        // Act
        $emailType->send([
            'email' => 'blocked@example.com',
            'token' => str_repeat('a', 64),
            'expiresAt' => new DateTimeImmutable('2026-01-02 12:00:00'),
            'lang' => 'en',
        ]);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType($this->createStub(EmailQueueInterface::class));

        // Act & Assert
        static::assertSame(EmailType::SupportEmailVerify->value, $emailType->getIdentifier());
    }

    public function testMockDataCarriesNoRequesterSuppliedField(): void
    {
        // Arrange
        $emailType = $this->createEmailType($this->createStub(EmailQueueInterface::class));

        // Act
        $mock = $emailType->getDisplayMockData('en');

        // Assert
        static::assertSame(['host', 'url', 'lang', 'token', 'expiresAt'], array_keys($mock['context']));
    }

    private function createEmailType(EmailQueueInterface $queue, bool $blocked = false): SupportEmailVerifyEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        $blocklist = $this->createStub(BlocklistCheckerInterface::class);
        $blocklist->method('isBlocked')->willReturn($blocked);

        $host = $this->createStub(RequestHostResolver::class);
        $host->method('getSchemeAndHost')->willReturn('https://platform.example.com');
        $host->method('getHost')->willReturn('platform.example.com');

        return new SupportEmailVerifyEmail($blocklist, $this->mockSampleFactory(), $queue, $config, $host);
    }
}
