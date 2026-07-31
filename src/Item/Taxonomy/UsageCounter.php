<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;

final readonly class UsageCounter
{
    public function __construct(
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
    ) {}

    /** @return array<int, int> definition id => how many items carry it */
    public function counts(string $itemType, Axis $axis): array
    {
        return $axis === Axis::Category
            ? $this->categoryRepo->countsForType($itemType)
            : $this->tagRepo->countsForType($itemType);
    }
}
