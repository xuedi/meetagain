<?php declare(strict_types=1);

namespace Module\Task\Contract;

final readonly class Health
{
    public function __construct(
        public int $workers,
        public int $schedulers,
        public ?int $failedMessages,
    ) {}
}
