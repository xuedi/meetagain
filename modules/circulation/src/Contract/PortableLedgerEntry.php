<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

use DateTimeImmutable;

final readonly class PortableLedgerEntry
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public LedgerEntryType $type,
        public string $context,
        public string $itemType,
        public int $itemId,
        public DateTimeImmutable $occurredAt,
        public ?int $copyRef = null,
        public ?int $fromUserId = null,
        public ?int $toUserId = null,
        public ?int $actorUserId = null,
        public ?int $handoverRef = null,
        public array $payload = [],
    ) {}
}
