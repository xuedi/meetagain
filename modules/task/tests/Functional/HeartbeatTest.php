<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use Module\Task\Internal\Heartbeat;
use Redis;
use RuntimeException;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

final class HeartbeatTest extends KernelTestCase
{
    private Heartbeat $heartbeat;
    private MockClock $clock;
    private Worker $worker;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock('2026-10-18 12:00:00');
        $this->heartbeat = new Heartbeat(self::getContainer()->get(Redis::class), $this->clock);
        $this->worker = new Worker(['async' => new InMemoryTransport()], new MessageBus());
    }

    protected function tearDown(): void
    {
        $this->heartbeat->onStopped(new WorkerStoppedEvent($this->worker));
        parent::tearDown();
    }

    public function testAStartedWorkerIsAliveWithTheTransportsItConsumes(): void
    {
        // Act
        $this->heartbeat->onStarted(new WorkerStartedEvent($this->worker));

        // Assert
        $own = $this->own();
        self::assertNotNull($own);
        self::assertSame(['async'], $own['transports']);
        self::assertSame(0, $own['handled']);
    }

    public function testTheCountsReachTheNextBeat(): void
    {
        // Arrange
        $this->heartbeat->onStarted(new WorkerStartedEvent($this->worker));
        $envelope = new Envelope(new stdClass());
        $this->heartbeat->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));
        $this->heartbeat->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));
        $this->heartbeat->onFailed(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('broke')));
        $this->clock->sleep(15);

        // Act
        $this->heartbeat->onRunning(new WorkerRunningEvent($this->worker, true));

        // Assert
        $own = $this->own();
        self::assertSame(2, $own['handled'] ?? null);
        self::assertSame(1, $own['failed'] ?? null);
        self::assertSame('2026-10-18T12:00:15+00:00', $own['seenAt'] ?? null);
    }

    public function testABeatIsNotWrittenMoreOftenThanEveryFifteenSeconds(): void
    {
        // Arrange
        $this->heartbeat->onStarted(new WorkerStartedEvent($this->worker));
        $this->heartbeat->onHandled(new WorkerMessageHandledEvent(new Envelope(new stdClass()), 'async'));
        $this->clock->sleep(14);

        // Act
        $this->heartbeat->onRunning(new WorkerRunningEvent($this->worker, true));

        // Assert
        self::assertSame(0, $this->own()['handled'] ?? null);
    }

    public function testAStoppedWorkerIsGone(): void
    {
        // Arrange
        $this->heartbeat->onStarted(new WorkerStartedEvent($this->worker));

        // Act
        $this->heartbeat->onStopped(new WorkerStoppedEvent($this->worker));

        // Assert
        self::assertNull($this->own());
    }

    /**
     * @return array{host: string, pid: int, transports: list<string>, startedAt: string, seenAt: string, handled: int, failed: int, memoryBytes: int}|null
     */
    private function own(): ?array
    {
        foreach ($this->heartbeat->alive() as $process) {
            if ($process['host'] === gethostname() && $process['pid'] === getmypid()) {
                return $process;
            }
        }

        return null;
    }
}
