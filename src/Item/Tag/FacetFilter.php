<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Item\FilterInterface;
use App\Repository\ItemTagAssignmentRepository;
use Override;

final readonly class FacetFilter implements FilterInterface
{
    public function __construct(
        private FacetService $facetService,
        private TypeRegistry $registry,
        private ItemTagAssignmentRepository $assignmentRepo,
    ) {}

    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        $selection = $this->facetService->current();
        if (!$this->registry->has($itemType) || $selection->tags === []) {
            return null;
        }

        return $this->assignmentRepo->itemIdsWithAllTags($itemType, $selection->tags);
    }
}
