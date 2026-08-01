<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;

readonly class TagPortability
{
    public function __construct(
        private ItemTagAssignmentRepository $tagRepo,
        private TagService $tagService,
    ) {}

    /**
     * @param list<int> $itemIds
     * @return array{tags: array<int, list<int>>}
     */
    public function export(string $itemType, array $itemIds): array
    {
        return ['tags' => $this->tagRepo->tagIdsForItems($itemType, $itemIds)];
    }

    /**
     * @param array<string, mixed> $block the items.<type> section of the export
     * @param array<int, int> $refToItemId
     * @return int assignments dropped because this instance does not define the id
     */
    // Never overwrites: the incoming tags merge with the ones the item already carries
    public function import(string $itemType, array $block, array $refToItemId): int
    {
        $known = array_keys($this->tagService->getChoices($itemType, null));
        $dropped = 0;

        $tags = is_array($block['tags'] ?? null) ? $block['tags'] : [];
        foreach ($tags as $ref => $tagIds) {
            $itemId = $refToItemId[(int) $ref] ?? null;
            $wanted = [];
            foreach (is_array($tagIds) ? $tagIds : [] as $tagId) {
                if ($itemId === null || !in_array((int) $tagId, $known, true)) {
                    ++$dropped;
                    continue;
                }

                $wanted[] = (int) $tagId;
            }

            if ($itemId === null || $wanted === []) {
                continue;
            }

            $merged = array_values(array_unique([...$this->tagService->getTagIds($itemType, $itemId), ...$wanted]));
            $this->tagService->setTags($itemType, $itemId, $merged);
        }

        return $dropped;
    }
}
