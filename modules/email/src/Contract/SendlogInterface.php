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

    /**
     * Deletes every mail still waiting for the address and strips the address, the body and the context from the
     * rest. Returns the number of rows touched.
     */
    public function forgetRecipient(string $address, string $replacement): int;

    /** Deletes every mail still waiting that was composed for a site on this host. Returns the number deleted. */
    public function forgetPendingForHost(string $host): int;

    /** Deletes every mail, sent or waiting. Returns the number deleted. */
    public function forgetAll(): int;
}
