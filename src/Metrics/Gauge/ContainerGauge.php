<?php declare(strict_types=1);

namespace App\Metrics\Gauge;

use App\Metrics\GaugeInterface;
use App\Metrics\Point;

final readonly class ContainerGauge implements GaugeInterface
{
    public function __construct(
        private string $cgroupDir = '/sys/fs/cgroup',
    ) {}

    public function collect(): iterable
    {
        $fields = [];

        $used = $this->readBytes('memory.current');
        if ($used !== null) {
            $fields['memory_used_mb'] = round($used / 1_048_576, 1);
        }
        $limit = $this->readBytes('memory.max');
        if ($limit !== null) {
            $fields['memory_limit_mb'] = round($limit / 1_048_576, 1);
        }

        $load = sys_getloadavg();
        if ($load !== false) {
            $fields['load1'] = round($load[0], 2);
            $fields['load5'] = round($load[1], 2);
        }

        if ($fields !== []) {
            yield new Point('container', $fields);
        }
    }

    private function readBytes(string $file): ?int
    {
        $path = $this->cgroupDir . '/' . $file;
        $content = is_readable($path) ? trim((string) file_get_contents($path)) : '';

        return ctype_digit($content) ? (int) $content : null;
    }
}
