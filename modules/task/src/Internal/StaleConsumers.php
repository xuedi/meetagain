<?php declare(strict_types=1);

namespace Module\Task\Internal;

use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Redis;

readonly class StaleConsumers implements TaskInterface
{
    public const int IDLE_SECONDS = 3600;

    public function __construct(
        private Redis $redis,
        private Heartbeat $heartbeat,
    ) {}

    public function getIdentifier(): string
    {
        return 'task.stale-consumers';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('40 * * * *');
    }

    public function getDescription(): string
    {
        return sprintf(
            'Keeps the worker list of the task stream readable. Every hour it removes the consumers of workers that are gone - a pod or container that no longer sends a heartbeat, has been idle for over %d minutes and holds no unfinished message. A consumer that still holds a message is never removed, so nothing is dropped.',
            intdiv(self::IDLE_SECONDS, 60),
        );
    }

    public function run(): Result
    {
        $consumers = $this->redis->xinfo('CONSUMERS', StreamTransportFactory::STREAM, StreamTransportFactory::GROUP);
        if (!is_array($consumers)) {
            return new Result(Status::ok, 'No task stream yet');
        }

        $liveHosts = array_map(static fn(array $process): string => rawurlencode($process['host']), $this->heartbeat->alive());
        $removed = 0;
        foreach ($consumers as $consumer) {
            $name = (string) ($consumer['name'] ?? '');
            $isStale = (int) ($consumer['pending'] ?? 1) === 0 && (int) ($consumer['idle'] ?? 0) > (self::IDLE_SECONDS * 1000);
            if ($name === '' || !$isStale || in_array($name, $liveHosts, true)) {
                continue;
            }

            $this->redis->xgroup('DELCONSUMER', StreamTransportFactory::STREAM, StreamTransportFactory::GROUP, $name);
            $removed++;
        }

        return new Result(Status::ok, sprintf('%d of %d consumers removed', $removed, count($consumers)));
    }
}
