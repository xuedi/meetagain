<?php declare(strict_types=1);

namespace App\Metrics\Gauge;

use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use Redis;

final readonly class ValkeyGauge implements GaugeInterface
{
    private const array COUNTERS = ['evicted_keys', 'keyspace_hits', 'keyspace_misses', 'connected_clients'];

    public function __construct(
        private Redis $redis,
    ) {}

    public function collect(): iterable
    {
        $info = $this->redis->info();
        if (!is_array($info)) {
            return;
        }

        $fields = [
            'used_memory_mb' => round((int) ($info['used_memory'] ?? 0) / 1_048_576, 1),
            'maxmemory_mb' => round((int) ($info['maxmemory'] ?? 0) / 1_048_576, 1),
        ];
        foreach (self::COUNTERS as $counter) {
            $fields[$counter] = (int) ($info[$counter] ?? 0);
        }

        yield new Point('valkey', $fields);
    }
}
