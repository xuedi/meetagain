<?php declare(strict_types=1);

namespace Module\Task\Contract;

final readonly class Cadence
{
    public function __construct(
        public string $cron,
        public int $jitterSeconds = 0,
        public bool $offloaded = false,
    ) {}
}
