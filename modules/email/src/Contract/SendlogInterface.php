<?php declare(strict_types=1);

namespace Module\Email\Contract;

use DateTimeImmutable;

interface SendlogInterface
{
    public function find(int $id): ?SentEmail;

    /**
     * Newest first. A filter left null does not narrow the list; the recipient filter matches the address with or
     * without a display name.
     *
     * @return list<SentEmail>
     */
    public function list(int $limit = 100, ?string $recipient = null, ?string $template = null, ?QueueStatus $status = null): array;

    public function countAll(): int;

    public function countBetween(DateTimeImmutable $from, DateTimeImmutable $to): int;

    public function stats(): QueueStats;
}
