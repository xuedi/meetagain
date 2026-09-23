<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Enum\ItemAction;
use App\Item\ActionInterface;
use App\Repository\ItemTagAssignmentRepository;
use App\Repository\ItemTagRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class TagService implements ActionInterface
{
    /**
     * @param iterable<FilterInterface>          $filters
     * @param iterable<AdminFilterInterface>     $adminFilters
     * @param iterable<CreationHandlerInterface> $creationHandlers
     * @param iterable<DeletionHandlerInterface>  $deletionHandlers
     */
    public function __construct(
        private EntityManagerInterface $em,
        private ItemTagRepository $tagRepo,
        private ItemTagAssignmentRepository $assignmentRepo,
        private AssignmentClosure $closure,
        private TypeRegistry $registry,
        private LanguageService $languageService,
        #[AutowireIterator(FilterInterface::class)]
        private iterable $filters,
        #[AutowireIterator(AdminFilterInterface::class)]
        private iterable $adminFilters,
        #[AutowireIterator(CreationHandlerInterface::class)]
        private iterable $creationHandlers,
        #[AutowireIterator(DeletionHandlerInterface::class)]
        private iterable $deletionHandlers,
    ) {}

    /** @return list<ItemTag> depth-first, every tag directly after its parent */
    public function getVocabulary(string $itemType): array
    {
        return $this->vocabulary($itemType, $this->allowedIds($itemType, $this->filters));
    }

    /** @return list<ItemTag> depth-first, every tag directly after its parent */
    public function getManagedVocabulary(string $itemType): array
    {
        $vocabulary = $this->vocabulary($itemType, $this->allowedIds($itemType, $this->adminFilters));

        return array_values(array_filter($vocabulary, static fn(ItemTag $tag): bool => !$tag->isManaged()));
    }

    public function getManagedTag(string $itemType, int $tagId): ?ItemTag
    {
        foreach ($this->getManagedVocabulary($itemType) as $tag) {
            if ($tag->getId() === $tagId) {
                return $tag;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function getTagIds(string $itemType, int $itemId): array
    {
        return $this->assignmentRepo->tagIdsFor($itemType, $itemId);
    }

    /** @param list<int> $tagIds */
    public function setTags(string $itemType, int $itemId, array $tagIds): void
    {
        $known = $this->knownTags($itemType);
        $wanted = $this->withAncestors($known, $tagIds);

        $current = [];
        foreach ($this->assignmentRepo->findFor($itemType, $itemId) as $assignment) {
            $current[(int) $assignment->getTagId()] = $assignment;
        }

        foreach ($current as $tagId => $assignment) {
            if (isset($wanted[$tagId]) || ($known[$tagId] ?? null)?->isManaged() === true) {
                continue;
            }

            $this->em->remove($assignment);
        }

        foreach ($wanted as $tagId => $tag) {
            if (isset($current[$tagId])) {
                continue;
            }

            $this->persistAssignment($itemType, $itemId, $tag);
        }

        $this->em->flush();
    }

    /** @param array<int, list<int>> $tagIdsByItem item id => tag ids to add; existing assignments stay */
    public function addTags(string $itemType, array $tagIdsByItem): void
    {
        $tagIdsByItem = array_filter($tagIdsByItem, static fn(array $tagIds): bool => $tagIds !== []);
        if ($tagIdsByItem === []) {
            return;
        }

        $known = $this->knownTags($itemType);
        $current = $this->assignmentRepo->tagIdsForItems($itemType, array_keys($tagIdsByItem));
        foreach ($tagIdsByItem as $itemId => $tagIds) {
            $assigned = array_flip($current[$itemId] ?? []);
            foreach ($this->withAncestors($known, $tagIds) as $tagId => $tag) {
                if (isset($assigned[$tagId])) {
                    continue;
                }

                $this->persistAssignment($itemType, $itemId, $tag);
            }
        }

        $this->em->flush();
    }

    /** @return list<string> the labels of the assigned tags the closure did not merely imply */
    public function getLabels(string $itemType, int $itemId, ?string $locale): array
    {
        return $this->getLabelsForItems($itemType, [$itemId], $locale)[$itemId] ?? [];
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, list<string>> item id => labels, for the items that carry any
     */
    public function getLabelsForItems(string $itemType, array $itemIds, ?string $locale): array
    {
        $assignedByItem = $this->assignmentRepo->tagIdsForItems($itemType, $itemIds);
        if ($assignedByItem === []) {
            return [];
        }

        $vocabulary = $this->getVocabulary($itemType);
        $sourceLocale = $this->sourceLocale();

        $labelsByItem = [];
        foreach ($assignedByItem as $itemId => $assigned) {
            $labels = $this->labelsOf($vocabulary, $assigned, $locale, $sourceLocale);
            if ($labels !== []) {
                $labelsByItem[$itemId] = $labels;
            }
        }

        return $labelsByItem;
    }

    public function labelFor(ItemTag $tag, ?string $locale): string
    {
        return $tag->getLabel($locale, $this->sourceLocale());
    }

    /** @return array<int, string> tag id => label, depth-first */
    public function getChoices(string $itemType, ?string $locale): array
    {
        $choices = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            $choices[(int) $tag->getId()] = $tag->getLabel($locale, $this->sourceLocale());
        }

        return $choices;
    }

    /** @return array<int, string> tag id => label, depth-first, managed rows left out */
    public function getAssignableChoices(string $itemType, ?string $locale): array
    {
        $choices = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            if ($tag->isManaged()) {
                continue;
            }

            $choices[(int) $tag->getId()] = $tag->getLabel($locale, $this->sourceLocale());
        }

        return $choices;
    }

    /** @return array<int, int> tag id => how many levels deep it sits */
    public function getDepths(string $itemType): array
    {
        $depths = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            $depths[(int) $tag->getId()] = $tag->getDepth();
        }

        return $depths;
    }

    /** @return array<int, ?int> tag id => its parent's id */
    public function getParents(string $itemType): array
    {
        $parents = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            $parents[(int) $tag->getId()] = $tag->getParent()?->getId();
        }

        return $parents;
    }

    /** @return list<array{id: int, label: string, depth: int}> depth-first */
    public function getChoiceRows(string $itemType, ?string $locale): array
    {
        $rows = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            $rows[] = [
                'id' => (int) $tag->getId(),
                'label' => $tag->getLabel($locale, $this->sourceLocale()),
                'depth' => $tag->getDepth(),
            ];
        }

        return $rows;
    }

    /** @return array<int, int> tag id => how many items carry it */
    public function getUsage(string $itemType): array
    {
        return $this->assignmentRepo->countsForType($itemType);
    }

    /** @param array<string, string> $labels */
    public function addTag(string $itemType, array $labels, ?ItemTag $parent, bool $managed = false): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType($itemType);
        $tag->setLabels($this->trimmedLabels($labels));
        $tag->setParent($parent);
        $tag->setManaged($managed);
        $tag->setPosition($this->tagRepo->nextPosition($itemType));

        $this->em->persist($tag);
        $this->em->flush();
        $this->announceCreation($tag);

        return $tag;
    }

    public function renameTag(ItemTag $tag, string $locale, string $label): void
    {
        $tag->setLabel($locale, trim($label));
        $this->em->flush();
    }

    public function moveTag(ItemTag $tag, ?ItemTag $parent): void
    {
        if ($parent !== null && !$this->canParent($tag, $parent)) {
            return;
        }

        $this->closure->reshape((string) $tag->getItemType(), function () use ($tag, $parent): void {
            $tag->setParent($parent);
            $this->em->flush();
        });
    }

    public function canParent(ItemTag $tag, ItemTag $parent): bool
    {
        $sameType = $parent->getItemType() === $tag->getItemType();
        $wouldCycle = $parent->getId() === $tag->getId() || in_array((int) $parent->getId(), $this->tagRepo->descendantIds($tag), true);

        return $sameType && !$wouldCycle && $parent->getDepth() < ItemTag::MAX_DEPTH;
    }

    public function deleteTag(ItemTag $tag): void
    {
        $ids = [(int) $tag->getId(), ...$this->tagRepo->descendantIds($tag)];
        $this->assignmentRepo->deleteForTags((string) $tag->getItemType(), $ids);

        foreach ($this->tagRepo->findBy(['id' => $ids]) as $doomed) {
            $this->announceDeletion($doomed);
            $this->em->remove($doomed);
        }
        $this->em->flush();
    }

    /** @param list<array{id?: int|string|null, parent?: int|string|null, labels?: array<string, string>|null}> $rows */
    public function saveVocabulary(string $itemType, array $rows): void
    {
        $existing = [];
        foreach ($this->getManagedVocabulary($itemType) as $tag) {
            $existing[(int) $tag->getId()] = $tag;
        }

        $byKey = [];
        $pairs = [];
        $position = 0;
        foreach ($rows as $row) {
            $labels = $this->trimmedLabels($row['labels'] ?? []);
            if ($labels === []) {
                continue;
            }

            $submittedId = (string) ($row['id'] ?? '');
            $tag = is_numeric($submittedId) ? $existing[(int) $submittedId] ?? null : null;
            $created = $tag === null;
            if ($tag === null) {
                $tag = new ItemTag();
                $tag->setItemType($itemType);
                $this->em->persist($tag);
            }

            $tag->setLabels($labels);
            $tag->setPosition($position++);
            if ($submittedId !== '') {
                $byKey[$submittedId] = $tag;
            }

            $pairs[] = ['tag' => $tag, 'parent' => (string) ($row['parent'] ?? ''), 'created' => $created];
        }

        $this->em->flush();

        foreach ($pairs as $pair) {
            if (!$pair['created']) {
                continue;
            }

            $this->announceCreation($pair['tag']);
        }

        $kept = array_column($pairs, 'tag');
        $this->closure->reshape($itemType, function () use ($pairs, $byKey, $kept): void {
            foreach ($pairs as $pair) {
                $parent = $byKey[$pair['parent']] ?? null;
                $pair['tag']->setParent($parent === $pair['tag'] ? null : $parent);
            }
            $this->em->flush();
            $this->repairForest($kept);
        });

        foreach ($existing as $tag) {
            if (in_array($tag, $kept, true)) {
                continue;
            }

            $this->deleteTag($tag);
        }
    }

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted) {
            return;
        }

        $this->assignmentRepo->deleteFor($itemType, $itemId);
    }

    /**
     * @param list<ItemTag> $vocabulary
     * @param list<int>     $assigned
     * @return list<string>
     */
    private function labelsOf(array $vocabulary, array $assigned, ?string $locale, string $sourceLocale): array
    {
        $implied = [];
        $tags = [];
        foreach ($vocabulary as $tag) {
            if (!in_array((int) $tag->getId(), $assigned, true)) {
                continue;
            }

            $tags[] = $tag;
            foreach ($tag->getAncestors() as $ancestor) {
                $implied[(int) $ancestor->getId()] = true;
            }
        }

        $labels = [];
        foreach ($tags as $tag) {
            $label = $tag->getLabel($locale, $sourceLocale);
            if (isset($implied[(int) $tag->getId()]) || $label === '') {
                continue;
            }

            $labels[] = $label;
        }

        return $labels;
    }

    /**
     * @param  list<int>|null $allowed
     * @return list<ItemTag>
     */
    private function vocabulary(string $itemType, ?array $allowed): array
    {
        if (!$this->registry->has($itemType)) {
            return [];
        }

        $tags = $this->tagRepo->findForType($itemType);
        if ($allowed !== null) {
            $tags = array_values(array_filter($tags, static fn(ItemTag $tag): bool => in_array((int) $tag->getId(), $allowed, true)));
        }

        return $this->ordered($tags);
    }

    /**
     * @param  list<ItemTag> $tags
     * @return list<ItemTag>
     */
    private function ordered(array $tags): array
    {
        $children = [];
        $present = [];
        foreach ($tags as $tag) {
            $present[(int) $tag->getId()] = true;
        }
        foreach ($tags as $tag) {
            $parentId = $tag->getParent()?->getId();
            $children[isset($present[$parentId]) ? (int) $parentId : 0][] = $tag;
        }

        $ordered = [];
        $this->appendBranch(0, $children, $ordered);

        return $ordered;
    }

    /**
     * @param array<int, list<ItemTag>> $children
     * @param list<ItemTag>             $ordered
     */
    private function appendBranch(int $parentId, array $children, array &$ordered): void
    {
        foreach ($children[$parentId] ?? [] as $tag) {
            if (in_array($tag, $ordered, true)) {
                continue;
            }

            $ordered[] = $tag;
            $this->appendBranch((int) $tag->getId(), $children, $ordered);
        }
    }

    /**
     * @param  iterable<FilterInterface|AdminFilterInterface> $chain
     * @return list<int>|null
     */
    private function allowedIds(string $itemType, iterable $chain): ?array
    {
        $result = null;
        foreach ($chain as $filter) {
            $ids = $filter->getAllowedTagIds($itemType);
            if ($ids === null) {
                continue;
            }

            $result = $result === null ? array_values($ids) : array_values(array_intersect($result, $ids));
        }

        return $result;
    }

    /** @return array<int, ItemTag> */
    private function knownTags(string $itemType): array
    {
        $known = [];
        foreach ($this->getVocabulary($itemType) as $tag) {
            $known[(int) $tag->getId()] = $tag;
        }

        return $known;
    }

    /**
     * @param array<int, ItemTag> $known
     * @param list<int> $tagIds
     * @return array<int, ItemTag> the assignable tags among $tagIds plus their ancestors
     */
    private function withAncestors(array $known, array $tagIds): array
    {
        $wanted = [];
        foreach (array_unique($tagIds) as $tagId) {
            if (!isset($known[$tagId]) || $known[$tagId]->isManaged()) {
                continue;
            }

            foreach ([$known[$tagId], ...$known[$tagId]->getAncestors()] as $tag) {
                $wanted[(int) $tag->getId()] = $tag;
            }
        }

        return $wanted;
    }

    private function persistAssignment(string $itemType, int $itemId, ItemTag $tag): void
    {
        $assignment = new ItemTagAssignment();
        $assignment->setItemType($itemType);
        $assignment->setItemId($itemId);
        $assignment->setTag($tag);
        $this->em->persist($assignment);
    }

    private function announceCreation(ItemTag $tag): void
    {
        foreach ($this->creationHandlers as $handler) {
            $handler->onTagCreated($tag);
        }
    }

    private function announceDeletion(ItemTag $tag): void
    {
        foreach ($this->deletionHandlers as $handler) {
            $handler->onTagDeleted($tag);
        }
    }

    /** @param list<ItemTag> $tags */
    private function repairForest(array $tags): void
    {
        $changed = false;
        foreach ($tags as $tag) {
            if ($this->isWellPlaced($tag)) {
                continue;
            }

            $tag->setParent(null);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $this->em->flush();
    }

    private function isWellPlaced(ItemTag $tag): bool
    {
        $walked = [(int) $tag->getId() => true];
        $depth = 1;
        for ($current = $tag->getParent(); $current !== null; $current = $current->getParent()) {
            $depth++;
            $cycles = isset($walked[(int) $current->getId()]);
            if ($depth > ItemTag::MAX_DEPTH || $cycles) {
                return false;
            }

            $walked[(int) $current->getId()] = true;
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed> $labels
     * @return array<string, string>
     */
    private function trimmedLabels(array $labels): array
    {
        $trimmed = [];
        foreach ($labels as $locale => $label) {
            $value = trim((string) $label);
            if ($value === '') {
                continue;
            }

            $trimmed[(string) $locale] = $value;
        }

        return $trimmed;
    }

    private function sourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }
}
