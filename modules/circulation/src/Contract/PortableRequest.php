<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

use DateTimeImmutable;

final readonly class PortableRequest
{
    public function __construct(
        public int $ref,
        public string $context,
        public string $itemType,
        public int $itemId,
        public int $userId,
        public DateTimeImmutable $requestedAt,
        public RequestStatus $status,
        public ?int $offeredCopyRef = null,
        public ?DateTimeImmutable $offeredAt = null,
    ) {}
}
