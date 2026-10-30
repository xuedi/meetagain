<?php declare(strict_types=1);

namespace Module\Proposal\Contract\Change;

use DateTimeImmutable;

final readonly class PortableChange
{
    /**
     * @param list<FieldChange> $changes
     */
    public function __construct(
        public string $targetType,
        public int $targetId,
        public int $proposerId,
        public array $changes,
        public DateTimeImmutable $createdAt,
    ) {}
}
