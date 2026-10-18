<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use Module\Task\Internal\Heartbeat;
use Module\Task\Internal\Queue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class QueueTest extends TestCase
{
    public function testHealthCountsWorkersAndSchedulersByTheTransportTheyConsume(): void
    {
        // Arrange
        $heartbeat = $this->createStub(Heartbeat::class);
        $heartbeat
            ->method('alive')
            ->willReturn([
                $this->process(['async']),
                $this->process(['async']),
                $this->process([Queue::SCHEDULER_TRANSPORT]),
            ]);
        $queue = new Queue($heartbeat, $this->createStub(MessageBusInterface::class), new InMemoryTransport(), new InMemoryTransport());

        // Act
        $health = $queue->health();

        // Assert
        self::assertSame(2, $health->workers);
        self::assertSame(1, $health->schedulers);
        self::assertNull($health->failedMessages);
    }

    /**
     * @param list<string> $transports
     *
     * @return array{host: string, pid: int, transports: list<string>, startedAt: string, seenAt: string, handled: int, failed: int, memoryBytes: int}
     */
    private function process(array $transports): array
    {
        return [
            'host' => 'worker-1',
            'pid' => 1,
            'transports' => $transports,
            'startedAt' => '2026-10-17T12:00:00+00:00',
            'seenAt' => '2026-10-17T12:00:15+00:00',
            'handled' => 0,
            'failed' => 0,
            'memoryBytes' => 0,
        ];
    }
}
