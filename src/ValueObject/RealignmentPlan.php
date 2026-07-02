<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\EventInterval;
use App\Enum\RealignmentOutcome;

final readonly class RealignmentPlan
{
    /**
     * @param list<RealignmentItem> $items
     */
    public function __construct(
        public ?int $seriesId,
        public int $anchorEventId,
        public ?EventInterval $rule,
        public array $items,
    ) {}

    /**
     * @return list<RealignmentItem>
     */
    public function movedItems(): array
    {
        return array_values(array_filter($this->items, static fn(RealignmentItem $item): bool => $item->outcome === RealignmentOutcome::Moved));
    }

    public function movedCount(): int
    {
        return count($this->movedItems());
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
