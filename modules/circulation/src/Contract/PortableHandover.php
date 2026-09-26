<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

use DateTimeImmutable;

final readonly class PortableHandover
{
    public function __construct(
        public int $ref,
        public int $copyRef,
        public int $toUserId,
        public DateTimeImmutable $openedAt,
        public HandoverStatus $status,
        public ?int $fromUserId = null,
        public ?int $requestRef = null,
        public ?DateTimeImmutable $fromConfirmedAt = null,
        public ?DateTimeImmutable $toConfirmedAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?int $cancelledByUserId = null,
    ) {}
}
