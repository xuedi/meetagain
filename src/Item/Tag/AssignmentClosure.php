<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemTagAssignmentRepository;
use App\Repository\ItemTagRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class AssignmentClosure
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemTagRepository $tagRepo,
        private ItemTagAssignmentRepository $assignmentRepo,
    ) {}

    public function reshape(string $itemType, callable $change): void
    {
        $this->em->wrapInTransaction(function () use ($itemType, $change): void {
            $tags = $this->tagsOf($itemType);
            $before = $this->chains($tags);

            $change();

            $moved = array_keys(array_filter(
                $this->chains($tags),
                static fn(array $chain, int $tagId): bool => $chain !== $before[$tagId],
                ARRAY_FILTER_USE_BOTH,
            ));
            $this->rewriteItems($itemType, $tags, $before, $this->assignmentRepo->tagIdsForItemsCarrying($itemType, $moved));
        });
    }

    public function restore(string $itemType): void
    {
        $tags = $this->tagsOf($itemType);
        $this->rewriteItems($itemType, $tags, $this->chains($tags), $this->assignmentRepo->tagIdsForType($itemType));
    }

    /** @return array<int, ItemTag> */
    private function tagsOf(string $itemType): array
    {
        $tags = [];
        foreach ($this->tagRepo->findForType($itemType) as $tag) {
            $tags[(int) $tag->getId()] = $tag;
        }

        return $tags;
    }

    /**
     * @param array<int, ItemTag> $tags
     * @return array<int, list<int>> tag id => its ancestor ids, nearest first
     */
    private function chains(array $tags): array
    {
        return array_map(static fn(ItemTag $tag): array => array_map(
            static fn(ItemTag $ancestor): int => (int) $ancestor->getId(),
            $tag->getAncestors(),
        ), $tags);
    }

    /**
     * @param array<int, ItemTag>   $tags
     * @param array<int, list<int>> $chains   the tree the items were tagged under
     * @param array<int, list<int>> $assigned item id => tag ids
     */
    private function rewriteItems(string $itemType, array $tags, array $chains, array $assigned): void
    {
        foreach ($assigned as $itemId => $tagIds) {
            $wanted = $this->closureOf($tags, $this->leafMost($tags, $chains, $tagIds));
            $this->rewrite($itemType, $itemId, $tagIds, $wanted, $tags);
        }
        $this->em->flush();
    }

    /**
     * @param array<int, ItemTag>   $tags
     * @param array<int, list<int>> $chains
     * @param list<int>             $tagIds
     * @return list<int>
     */
    private function leafMost(array $tags, array $chains, array $tagIds): array
    {
        $unmanaged = $this->unmanaged($tags, $tagIds);
        $implied = [];
        foreach ($unmanaged as $tagId) {
            foreach ($chains[$tagId] ?? [] as $ancestorId) {
                $implied[$ancestorId] = true;
            }
        }

        return array_values(array_filter($unmanaged, static fn(int $tagId): bool => !isset($implied[$tagId])));
    }

    /**
     * @param array<int, ItemTag> $tags
     * @param list<int>           $tagIds
     * @return array<int, ItemTag>
     */
    private function closureOf(array $tags, array $tagIds): array
    {
        $closure = [];
        foreach ($tagIds as $tagId) {
            foreach ([$tags[$tagId], ...$tags[$tagId]->getAncestors()] as $tag) {
                $closure[(int) $tag->getId()] = $tag;
            }
        }

        return $closure;
    }

    /**
     * @param list<int>           $assigned
     * @param array<int, ItemTag> $wanted
     * @param array<int, ItemTag> $tags
     */
    private function rewrite(string $itemType, int $itemId, array $assigned, array $wanted, array $tags): void
    {
        $doomed = array_diff($this->unmanaged($tags, $assigned), array_keys($wanted));
        if ($doomed !== []) {
            foreach ($this->assignmentRepo->findFor($itemType, $itemId) as $assignment) {
                if (!in_array((int) $assignment->getTagId(), $doomed, true)) {
                    continue;
                }

                $this->em->remove($assignment);
            }
        }

        foreach (array_diff_key($wanted, array_flip($assigned)) as $tag) {
            $this->em->persist(
                new ItemTagAssignment()
                    ->setItemType($itemType)
                    ->setItemId($itemId)
                    ->setTag($tag),
            );
        }
    }

    /**
     * @param array<int, ItemTag> $tags
     * @param list<int>           $tagIds
     * @return list<int>
     */
    private function unmanaged(array $tags, array $tagIds): array
    {
        return array_values(array_filter($tagIds, static fn(int $tagId): bool => isset($tags[$tagId]) && !$tags[$tagId]->isManaged()));
    }
}
