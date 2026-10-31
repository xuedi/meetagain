<?php declare(strict_types=1);

namespace Module\Task\Contract;

final readonly class Result
{
    public function __construct(
        public Status $status,
        public string $message,
        public ?string $continueFrom = null,
    ) {}
}
