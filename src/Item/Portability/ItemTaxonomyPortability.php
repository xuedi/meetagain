<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ItemTaxonomyService;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;

/**
 * Carries category and tag assignments across an export/import. Assignments reference definition
 * ids that live in each plugin's settings and do not travel, so an id this instance does not define
 * is dropped and counted rather than written.
 *
 * Import never overwrites what the target already classified: a category is only written to an item
 * that has none, and tags are merged with the ones already assigned.
 */
readonly class ItemTaxonomyPortability
{
    public function __construct(
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
        private ItemTaxonomyService $taxonomyService,
        private CategorizableTypeRegistry $registry,
    ) {}

    /**
     * @param list<int> $itemIds
     * @return array{categories: array<int, int>, tags: array<int, list<int>>}
     */
    public function export(string $itemType, array $itemIds): array
    {
        // Legacy "no category" rows carry id 0, which is not a definition id - exporting them
        // would land as dropped assignments on the far side and inflate the reported loss.
        $categories = array_filter(
            $this->categoryRepo->categoriesForItems($itemType, $itemIds),
            static fn(int $categoryId): bool => $categoryId > 0,
        );

        return [
            'categories' => $categories,
            'tags' => $this->tagRepo->tagIdsForItems($itemType, $itemIds),
        ];
    }

    /**
     * @param array<string, mixed> $block the items.<type> section of the export
     * @param array<int, int> $refToItemId
     * @return int assignments dropped because this instance does not define the id
     */
    public function import(string $itemType, array $block, array $refToItemId): int
    {
        $taxonomy = $this->registry->providerFor($itemType)?->getTaxonomy();
        $dropped = 0;

        $categories = is_array($block['categories'] ?? null) ? $block['categories'] : [];
        foreach ($categories as $ref => $categoryId) {
            $itemId = $refToItemId[(int) $ref] ?? null;
            if ($itemId === null || $taxonomy === null || !$taxonomy->hasCategory((int) $categoryId)) {
                ++$dropped;
                continue;
            }

            if ($this->taxonomyService->getCategory($itemType, $itemId) !== null) {
                continue;
            }

            $this->taxonomyService->setCategory($itemType, $itemId, (int) $categoryId);
        }

        $tags = is_array($block['tags'] ?? null) ? $block['tags'] : [];
        foreach ($tags as $ref => $tagIds) {
            $itemId = $refToItemId[(int) $ref] ?? null;
            $wanted = [];
            foreach (is_array($tagIds) ? $tagIds : [] as $tagId) {
                if ($itemId === null || $taxonomy === null || !$taxonomy->hasTag((int) $tagId)) {
                    ++$dropped;
                    continue;
                }

                $wanted[] = (int) $tagId;
            }

            if ($itemId === null || $wanted === []) {
                continue;
            }

            $merged = array_values(array_unique([...$this->taxonomyService->getTagIds($itemType, $itemId), ...$wanted]));
            $this->taxonomyService->setTags($itemType, $itemId, $merged);
        }

        return $dropped;
    }
}
