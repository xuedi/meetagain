<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use DateTimeImmutable;

final readonly class PortableGrant
{
    public function __construct(
        public string $context,
        public int $fromUserId,
        public int $toUserId,
        public TrustLevel $level,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
