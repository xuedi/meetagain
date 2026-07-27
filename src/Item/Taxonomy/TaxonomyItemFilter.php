<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Item\FilterInterface;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Override;

final readonly class TaxonomyItemFilter implements FilterInterface
{
    public function __construct(
        private FacetResolver $facetResolver,
        private CategorizableTypeRegistry $registry,
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
    ) {}

    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            return null;
        }

        $selection = $this->facetResolver->current();
        $result = null;

        if ($provider->supportsCategories() && $selection->category !== null) {
            $result = $this->categoryRepo->itemIdsWithCategory($itemType, $selection->category);
        }

        if ($provider->supportsTags() && $selection->tags !== []) {
            $tagAllowed = $this->tagRepo->itemIdsWithAllTags($itemType, $selection->tags);
            $result = $result === null ? $tagAllowed : array_values(array_intersect($result, $tagAllowed));
        }

        return $result;
    }
}
