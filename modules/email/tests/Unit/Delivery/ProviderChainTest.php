<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Delivery;

use DateTimeImmutable;
use Module\Email\Contract\DeliveryLog;
use Module\Email\Contract\DeliveryLogCollection;
use Module\Email\Contract\DeliveryLogFilter;
use Module\Email\Contract\DeliveryProviderInterface;
use Module\Email\Internal\Delivery\ProviderChain;
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
        $collection = $chain->getLogs(new DeliveryLogFilter());

        // Assert
        static::assertSame('first', $log?->messageId);
        static::assertSame('first', $collection->items[0]->messageId);
    }

    public function testWithNoProviderClaimingTheChainAnswersEmpty(): void
    {
        // Arrange
        $chain = new ProviderChain([$this->provider(false)]);

        // Act
        $collection = $chain->getLogs(new DeliveryLogFilter(offset: 5, size: 7));

        // Assert
        static::assertNull($chain->getLogByMessageId('any-id'));
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
        static::assertSame(5, $collection->offset);
        static::assertSame(7, $collection->size);
    }

    private function provider(bool $available, string $messageId = 'x'): DeliveryProviderInterface
    {
        $log = new DeliveryLog(
            messageId: $messageId,
            status: 'delivered',
            recipientEmail: 'someone@example.test',
            createdAt: new DateTimeImmutable('2026-05-12 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-05-12 10:00:00'),
            bounceType: null,
            mailboxProvider: null,
        );

        $provider = $this->createStub(DeliveryProviderInterface::class);
        $provider->method('isAvailable')->willReturn($available);
        $provider->method('getLogByMessageId')->willReturn($log);
        $provider->method('getLogs')->willReturn(new DeliveryLogCollection([$log], 1, 0, 50));

        return $provider;
    }
}
