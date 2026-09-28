<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

final readonly class View
{
    /**
     * @param list<array{label: string, value: string}> $rows
     */
    public function __construct(
        public int $id,
        public string $targetType,
        public Status $status,
        public string $description,
        public array $rows,
        public ?int $createdId,
    ) {}
}
