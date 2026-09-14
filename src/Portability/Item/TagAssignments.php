<?php declare(strict_types=1);

namespace App\Portability\Item;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class TagAssignments
{
    public const string KIND = 'tag_assignments';

    public function __construct(
        private ItemTagAssignmentRepository $assignmentRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param list<int> $itemIds
     * @param list<int> $allowedTagIds
     * @return array{tags: array<int, list<int>>}
     */
    public function export(string $itemType, array $itemIds, array $allowedTagIds): array
    {
        $tags = [];
        foreach ($this->assignmentRepository->tagIdsForItems($itemType, $itemIds) as $itemId => $tagIds) {
            $kept = array_values(array_intersect($tagIds, $allowedTagIds));
            if ($kept === []) {
                continue;
            }

            sort($kept);
            $tags[$itemId] = $kept;
        }
        ksort($tags);

        return ['tags' => $tags];
    }

    /**
     * @param array<array-key, mixed> $block the items.<type> section of the export
     * @param array<int, int> $refToItemId
     */
    // Never overwrites: the incoming tags merge with the ones the item already carries
    public function import(string $itemType, array $block, array $refToItemId, ImportContext $context): void
    {
        $tags = is_array($block['tags'] ?? null) ? $block['tags'] : [];
        foreach ($tags as $ref => $tagIds) {
            $tagIds = is_array($tagIds) ? $tagIds : [];
            $itemId = $refToItemId[(int) $ref] ?? null;
            if ($itemId === null) {
                $context->count(self::KIND, Outcome::Dropped, count($tagIds));
                continue;
            }

            $carried = $this->assignmentRepository->tagIdsFor($itemType, $itemId);
            foreach ($tagIds as $tagId) {
                $tag = $context->resolveRef(ItemTag::class, $tagId);
                if (!$tag instanceof ItemTag || $tag->getItemType() !== $itemType) {
                    $context->count(self::KIND, Outcome::Dropped);
                    continue;
                }

                $resolvedId = (int) $tag->getId();
                if (in_array($resolvedId, $carried, true)) {
                    $context->count(self::KIND, Outcome::Matched);
                    continue;
                }

                $carried[] = $resolvedId;
                $this->em->persist(new ItemTagAssignment()->setItemType($itemType)->setItemId($itemId)->setTag($tag));
                $context->count(self::KIND, Outcome::Created);
            }
        }
    }
}
