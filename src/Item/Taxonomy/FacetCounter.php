<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Item\ListRegistry;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;

class FacetCounter
{
    /** @var array<string, FacetCounts|null> */
    private array $memo = [];

    public function __construct(
        private readonly ListRegistry $listRegistry,
        private readonly CategorizableTypeRegistry $typeRegistry,
        private readonly FacetResolver $facetResolver,
        private readonly ItemCategoryAssignmentRepository $categoryRepo,
        private readonly ItemTagAssignmentRepository $tagRepo,
    ) {}

    public function counts(string $itemType): ?FacetCounts
    {
        if (array_key_exists($itemType, $this->memo)) {
            return $this->memo[$itemType];
        }

        return $this->memo[$itemType] = $this->compute($itemType);
    }

    private function compute(string $itemType): ?FacetCounts
    {
        $listProvider = $this->listRegistry->providerFor($itemType);
        $typeProvider = $this->typeRegistry->providerFor($itemType);
        if ($listProvider === null || $typeProvider === null) {
            return null;
        }

        $taxonomy = $typeProvider->getTaxonomy();
        $countCategories = $typeProvider->supportsCategories() && $taxonomy->isCategoriesEnabled() && $taxonomy->getCategories() !== [];
        $countTags = $typeProvider->supportsTags() && $taxonomy->isTagsEnabled() && $taxonomy->getTags() !== [];
        if (!$countCategories && !$countTags) {
            return null;
        }

        $visible = $this->facetResolver->withoutFacets($listProvider->getItemIds(...));
        $selection = $this->facetResolver->current();

        $tagged = $selection->tags === []
            ? $visible
            : array_values(array_intersect($visible, $this->tagRepo->itemIdsWithAllTags($itemType, $selection->tags)));

        $result = $selection->category === null
            ? $tagged
            : array_values(array_intersect($tagged, $this->categoryRepo->itemIdsWithCategory($itemType, $selection->category)));

        return new FacetCounts(
            categories: $countCategories ? $this->categoryRepo->countsByCategory($itemType, $tagged) : [],
            tags: $countTags ? $this->tagRepo->countsByTag($itemType, $result) : [],
            total: count($visible),
            shown: count($result),
        );
    }
}
