<?php declare(strict_types=1);

namespace Module\Task\Contract;

use DateTimeImmutable;

final readonly class FinishedRun
{
    public function __construct(
        public int $id,
        public string $identifier,
        public DateTimeImmutable $startedAt,
        public int $durationMs,
        public Status $status,
        public string $message,
        public string $host,
    ) {}
}
