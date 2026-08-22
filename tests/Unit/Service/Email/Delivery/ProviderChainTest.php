<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery;

use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use App\Service\Email\Delivery\Log;
use App\Service\Email\Delivery\LogCollection;
use App\Service\Email\Delivery\LogFilter;
use App\Service\Email\Delivery\ProviderChain;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ProviderChainTest extends TestCase
{
    public function testIsAvailableOnlyWhenSomeProviderClaims(): void
    {
        // Arrange
        $empty = new ProviderChain([]);
        $declining = new ProviderChain([$this->provider(false)]);
        $claiming = new ProviderChain([$this->provider(false), $this->provider(true)]);

        // Act & Assert
        static::assertFalse($empty->isAvailable());
        static::assertFalse($declining->isAvailable());
        static::assertTrue($claiming->isAvailable());
    }

    public function testFirstAvailableProviderAnswersAndLaterOnesAreSkipped(): void
    {
        // Arrange
        $chain = new ProviderChain([
            $this->provider(false, 'declined'),
            $this->provider(true, 'first'),
            $this->provider(true, 'second'),
        ]);

        // Act
        $log = $chain->getLogByMessageId('any-id');
        $collection = $chain->getLogs(new LogFilter());

        // Assert
        static::assertSame('first', $log?->messageId);
        static::assertSame('first', $collection->items[0]->messageId);
    }

    public function testWithNoProviderClaimingTheChainAnswersEmpty(): void
    {
        // Arrange
        $chain = new ProviderChain([$this->provider(false)]);

        // Act
        $collection = $chain->getLogs(new LogFilter(offset: 5, size: 7));

        // Assert
        static::assertNull($chain->getLogByMessageId('any-id'));
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
        static::assertSame(5, $collection->offset);
        static::assertSame(7, $collection->size);
    }

    private function provider(bool $available, string $messageId = 'x'): EmailDeliveryProviderInterface
    {
        $log = new Log(
            messageId: $messageId,
            status: 'delivered',
            recipientEmail: 'someone@example.test',
            createdAt: new DateTimeImmutable('2026-05-12 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-05-12 10:00:00'),
            bounceType: null,
            mailboxProvider: null,
        );

        $provider = $this->createStub(EmailDeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn($available);
        $provider->method('getLogByMessageId')->willReturn($log);
        $provider->method('getLogs')->willReturn(new LogCollection([$log], 1, 0, 50));

        return $provider;
    }
}
