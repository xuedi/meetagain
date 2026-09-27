<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

use DateTimeImmutable;

final readonly class PortableCopy
{
    public function __construct(
        public int $ref,
        public string $context,
        public string $itemType,
        public int $itemId,
        public DateTimeImmutable $donatedAt,
        public CopyStatus $status,
        public ?string $label = null,
        public ?int $donatedByUserId = null,
        public ?int $holderUserId = null,
        public ?DateTimeImmutable $heldSince = null,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}
}
