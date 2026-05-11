<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery;

use App\Service\Email\Delivery\EmailDeliveryLog;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailDeliveryLogTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-05-12 10:00:00');
        $updatedAt = new DateTimeImmutable('2026-05-12 10:05:00');

        // Act
        $log = new EmailDeliveryLog(
            messageId: 'tx-1',
            status: 'delivered',
            recipientEmail: 'to@example.test',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            bounceType: null,
            mailboxProvider: 'gmail',
            rawData: ['foo' => 'bar'],
        );

        // Assert
        static::assertSame('tx-1', $log->messageId);
        static::assertSame('delivered', $log->status);
        static::assertSame('to@example.test', $log->recipientEmail);
        static::assertSame($createdAt, $log->createdAt);
        static::assertSame($updatedAt, $log->updatedAt);
        static::assertNull($log->bounceType);
        static::assertSame('gmail', $log->mailboxProvider);
        static::assertSame(['foo' => 'bar'], $log->rawData);
    }

    #[DataProvider('provideIsDeliveredCases')]
    public function testIsDeliveredMatchesExactStatus(string $status, bool $expected): void
    {
        $log = $this->makeLog(status: $status);
        static::assertSame($expected, $log->isDelivered());
    }

    public static function provideIsDeliveredCases(): iterable
    {
        yield 'delivered' => ['delivered', true];
        yield 'bounced is not delivered' => ['bounced', false];
        yield 'pending is not delivered' => ['pending', false];
        yield 'capitalised does not match' => ['Delivered', false];
        yield 'empty is not delivered' => ['', false];
    }

    #[DataProvider('provideIsBouncedCases')]
    public function testIsBouncedTriggersOnAnyBounceType(?string $bounceType, bool $expected): void
    {
        $log = $this->makeLog(bounceType: $bounceType);
        static::assertSame($expected, $log->isBounced());
    }

    public static function provideIsBouncedCases(): iterable
    {
        yield 'null bounceType is not bounced' => [null, false];
        yield 'hard bounce' => ['hard', true];
        yield 'soft bounce' => ['soft', true];
        yield 'empty string still counts as bounced (non-null)' => ['', true];
    }

    private function makeLog(string $status = 'delivered', ?string $bounceType = null): EmailDeliveryLog
    {
        return new EmailDeliveryLog(
            messageId: 'x',
            status: $status,
            recipientEmail: 'x@y.z',
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            bounceType: $bounceType,
            mailboxProvider: null,
        );
    }
}
