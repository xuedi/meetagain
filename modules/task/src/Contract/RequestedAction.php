<?php declare(strict_types=1);

namespace Module\Task\Contract;

use DateTimeImmutable;

final readonly class RequestedAction
{
    public function __construct(
        public int $id,
        public string $identifier,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?Status $status,
        public ?string $message,
    ) {}

    public function isFinished(): bool
    {
        return $this->finishedAt !== null;
    }

    public function isRunning(): bool
    {
        return $this->startedAt !== null && $this->finishedAt === null;
    }
}
