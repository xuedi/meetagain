<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class ManagedWriter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemTagAssignmentRepository $assignmentRepo,
        private TagService $tagService,
    ) {}

    /** @param array<string, string> $labels one of which identifies the row on a later call */
    public function resolve(string $itemType, array $labels, ?ItemTag $parent): ItemTag
    {
        return $this->find($itemType, $labels, $parent)
            ?? $this->tagService->addTag($itemType, array_map(trim(...), $labels), $parent, true);
    }

    /** @param array<string, string> $labels */
    public function find(string $itemType, array $labels, ?ItemTag $parent): ?ItemTag
    {
        $labels = array_map(trim(...), $labels);
        foreach ($this->tagService->getVocabulary($itemType) as $tag) {
            if ($this->isRow($tag, $labels, $parent)) {
                return $tag;
            }
        }

        return null;
    }

    public function assign(ItemTag $tag, int $itemId): void
    {
        $itemType = (string) $tag->getItemType();
        $current = $this->assignmentRepo->tagIdsFor($itemType, $itemId);

        foreach ($this->closure($tag) as $tagId => $node) {
            if (in_array($tagId, $current, true)) {
                continue;
            }

            $assignment = new ItemTagAssignment();
            $assignment->setItemType($itemType);
            $assignment->setItemId($itemId);
            $assignment->setTag($node);
            $this->em->persist($assignment);
        }

        $this->em->flush();
    }

    public function unassign(ItemTag $tag, int $itemId): void
    {
        $itemType = (string) $tag->getItemType();
        $closure = $this->closure($tag);
        $doomed = array_diff_key($closure, $this->impliedWithout($itemType, $itemId, $closure));

        foreach ($this->assignmentRepo->findFor($itemType, $itemId) as $assignment) {
            if (!isset($doomed[(int) $assignment->getTagId()])) {
                continue;
            }

            $this->em->remove($assignment);
        }

        $this->em->flush();
    }

    /** @param array<string, string> $labels */
    private function isRow(ItemTag $tag, array $labels, ?ItemTag $parent): bool
    {
        $sameBranch = $tag->isManaged() && $tag->getParent()?->getId() === $parent?->getId();

        return $sameBranch && array_intersect($tag->getLabels(), $labels) !== [];
    }

    /** @return array<int, ItemTag> the tag and every ancestor, keyed by id */
    private function closure(ItemTag $tag): array
    {
        $closure = [];
        foreach ([$tag, ...$tag->getAncestors()] as $node) {
            $closure[(int) $node->getId()] = $node;
        }

        return $closure;
    }

    /**
     * @param  array<int, ItemTag> $ignored
     * @return array<int, true>    what the item's remaining assignments still stand for, ancestors included
     */
    private function impliedWithout(string $itemType, int $itemId, array $ignored): array
    {
        $known = [];
        foreach ($this->tagService->getVocabulary($itemType) as $tag) {
            $known[(int) $tag->getId()] = $tag;
        }

        $implied = [];
        foreach ($this->assignmentRepo->tagIdsFor($itemType, $itemId) as $assignedId) {
            if (isset($ignored[$assignedId]) || !isset($known[$assignedId])) {
                continue;
            }

            $implied += array_fill_keys(array_keys($this->closure($known[$assignedId])), true);
        }

        return $implied;
    }
}
