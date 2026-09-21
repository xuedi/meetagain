<?php declare(strict_types=1);

namespace App\Metrics\Gauge;

use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use App\Service\System\LogService;

final readonly class LogFilesGauge implements GaugeInterface
{
    public function __construct(
        private LogService $logService,
    ) {}

    public function collect(): iterable
    {
        yield new Point('log_files', [
            'size_mb' => round($this->logService->getTotalSize() / 1_048_576, 2),
            'files' => count($this->logService->getLogFiles()),
        ]);
    }
}
