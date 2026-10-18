<?php declare(strict_types=1);

namespace Module\Task\Internal;

use DateTimeImmutable;
use Redis;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

class Heartbeat
{
    public const int TTL_SECONDS = 90;
    private const string KEY_PREFIX = 'task:heartbeat:';
    private const int WRITE_EVERY_SECONDS = 15;

    /** @var list<string> */
    private array $transports = [];
    private ?DateTimeImmutable $startedAt = null;
    private int $handled = 0;
    private int $failed = 0;
    private int $lastWrite = 0;

    public function __construct(
        private readonly Redis $redis,
        private readonly ClockInterface $clock,
    ) {}

    #[AsEventListener]
    public function onStarted(WorkerStartedEvent $event): void
    {
        $this->transports = array_values($event->getWorker()->getMetadata()->getTransportNames());
        $this->startedAt = $this->clock->now();
        $this->write();
    }

    #[AsEventListener]
    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->handled++;
    }

    #[AsEventListener]
    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->failed++;
    }

    #[AsEventListener]
    public function onRunning(WorkerRunningEvent $event): void
    {
        if (($this->clock->now()->getTimestamp() - $this->lastWrite) >= self::WRITE_EVERY_SECONDS) {
            $this->write();
        }
    }

    #[AsEventListener]
    public function onStopped(WorkerStoppedEvent $event): void
    {
        $this->redis->del($this->key());
    }

    /**
     * @return list<array{host: string, pid: int, transports: list<string>, startedAt: string, seenAt: string, handled: int, failed: int, memoryBytes: int}>
     */
    public function alive(): array
    {
        $processes = [];
        $iterator = null;
        do {
            $keys = $this->redis->scan($iterator, self::KEY_PREFIX . '*', 100);
            foreach ($keys === false ? [] : $keys as $key) {
                $raw = $this->redis->get($key);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    $processes[] = $decoded;
                }
            }
        } while ($iterator > 0);

        usort($processes, static fn(array $a, array $b): int => [$a['host'], $a['pid']] <=> [$b['host'], $b['pid']]);

        return $processes;
    }

    private function write(): void
    {
        $now = $this->clock->now();
        $this->lastWrite = $now->getTimestamp();
        $this->redis->setex(
            $this->key(),
            self::TTL_SECONDS,
            (string) json_encode([
                'host' => (string) gethostname(),
                'pid' => (int) getmypid(),
                'transports' => $this->transports,
                'startedAt' => ($this->startedAt ?? $now)->format(DATE_ATOM),
                'seenAt' => $now->format(DATE_ATOM),
                'handled' => $this->handled,
                'failed' => $this->failed,
                'memoryBytes' => memory_get_usage(true),
            ]),
        );
    }

    private function key(): string
    {
        return self::KEY_PREFIX . gethostname() . ':' . getmypid();
    }
}
