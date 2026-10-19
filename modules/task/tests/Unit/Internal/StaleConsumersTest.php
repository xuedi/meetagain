<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use Module\Task\Internal\Heartbeat;
use Module\Task\Internal\StaleConsumers;
use PHPUnit\Framework\TestCase;
use Redis;

final class StaleConsumersTest extends TestCase
{
    private const int AN_HOUR_AND_MORE_MS = 3_700_000;

    public function testOnlyAnIdleConsumerWithoutPendingMessagesAndHeartbeatIsRemoved(): void
    {
        // Arrange
        $removed = [];
        $redis = $this->createStub(Redis::class);
        $redis
            ->method('xinfo')
            ->willReturn([
                ['name' => 'web-gone', 'pending' => 0, 'idle' => self::AN_HOUR_AND_MORE_MS],
                ['name' => 'web-holding', 'pending' => 2, 'idle' => self::AN_HOUR_AND_MORE_MS],
                ['name' => 'web-recent', 'pending' => 0, 'idle' => 60_000],
                ['name' => 'web-alive', 'pending' => 0, 'idle' => self::AN_HOUR_AND_MORE_MS],
            ]);
        $redis
            ->method('xgroup')
            ->willReturnCallback(static function (string $operation, string $stream, string $group, string $consumer) use (&$removed): int {
                $removed[] = $consumer;

                return 0;
            });
        $heartbeat = $this->createStub(Heartbeat::class);
        $heartbeat
            ->method('alive')
            ->willReturn([
                ['host' => 'web-alive', 'pid' => 1, 'transports' => [], 'startedAt' => '', 'seenAt' => '', 'handled' => 0, 'failed' => 0, 'memoryBytes' => 0],
            ]);

        // Act
        $result = new StaleConsumers($redis, $heartbeat)->run();

        // Assert
        static::assertSame(['web-gone'], $removed);
        static::assertSame('1 of 4 consumers removed', $result->message);
    }

    public function testAMissingStreamRemovesNothing(): void
    {
        // Arrange
        $redis = $this->createStub(Redis::class);
        $redis->method('xinfo')->willReturn(false);

        // Act
        $result = new StaleConsumers($redis, $this->createStub(Heartbeat::class))->run();

        // Assert
        static::assertSame('No task stream yet', $result->message);
    }
}
