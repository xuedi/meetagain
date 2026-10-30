<?php declare(strict_types=1);

namespace Module\Proposal\Contract\Change;

use DateTimeImmutable;
use Module\Proposal\Contract\Status;

final readonly class View
{
    /**
     * @param list<FieldChange> $changes
     * @param list<array{field: string, label: string, before: string, after: string, resolution: ?FieldResolution}> $rows
     */
    public function __construct(
        public int $id,
        public string $targetType,
        public int $targetId,
        public int $proposerId,
        public string $proposerName,
        public Status $status,
        public DateTimeImmutable $createdAt,
        public array $changes,
        public array $rows,
    ) {}
}
