<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use DateTimeImmutable;

final readonly class TrustAction
{
    public function __construct(
        public int $userId,
        public string $action,
        public DateTimeImmutable $occurredAt,
        public int $quantity = 1,
    ) {}
}
