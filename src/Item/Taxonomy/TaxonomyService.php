<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Entity\ItemCategoryAssignment;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;

readonly class TaxonomyService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
        private CategorizableTypeRegistry $registry,
        private LanguageService $languageService,
    ) {}

    public function getCategory(string $itemType, int $itemId): ?int
    {
        return $this->categoryRepo->categoryFor($itemType, $itemId);
    }

    public function setCategory(string $itemType, int $itemId, ?int $categoryId): void
    {
        $taxonomy = $this->taxonomyFor($itemType);
        if ($categoryId !== null && ($taxonomy === null || !$taxonomy->hasCategory($categoryId))) {
            $categoryId = null;
        }

        $existing = $this->categoryRepo->findOneFor($itemType, $itemId);

        if ($categoryId === null) {
            if ($existing !== null) {
                $this->em->remove($existing);
                $this->em->flush();
            }

            return;
        }

        if ($existing === null) {
            $existing = new ItemCategoryAssignment();
            $existing->setItemType($itemType);
            $existing->setItemId($itemId);
            $this->em->persist($existing);
        }
        $existing->setCategoryId($categoryId);
        $this->em->flush();
    }

    /** @return list<int> */
    public function getTagIds(string $itemType, int $itemId): array
    {
        return $this->tagRepo->tagIdsFor($itemType, $itemId);
    }

    /** @param list<int> $tagIds */
    public function setTags(string $itemType, int $itemId, array $tagIds): void
    {
        $taxonomy = $this->taxonomyFor($itemType);
        $wanted = [];
        foreach (array_unique($tagIds) as $tagId) {
            if (!($taxonomy !== null && $taxonomy->hasTag($tagId))) {
                continue;
            }

            foreach ([$tagId, ...$taxonomy->tagTree()->ancestors($tagId)] as $id) {
                if (in_array($id, $wanted, true)) {
                    continue;
                }

                $wanted[] = $id;
            }
        }

        $current = [];
        foreach ($this->tagRepo->findFor($itemType, $itemId) as $assignment) {
            $current[(int) $assignment->getTagId()] = $assignment;
        }

        foreach ($current as $tagId => $assignment) {
            if (in_array($tagId, $wanted, true)) {
                continue;
            }

            $this->em->remove($assignment);
        }

        foreach ($wanted as $tagId) {
            if (isset($current[$tagId])) {
                continue;
            }

            $assignment = new ItemTagAssignment();
            $assignment->setItemType($itemType);
            $assignment->setItemId($itemId);
            $assignment->setTagId($tagId);
            $this->em->persist($assignment);
        }

        $this->em->flush();
    }

    public function getCategoryLabel(string $itemType, int $itemId, ?string $locale): ?string
    {
        $categoryId = $this->getCategory($itemType, $itemId);
        if ($categoryId === null) {
            return null;
        }

        return $this->taxonomyFor($itemType)?->categoryLabel($categoryId, $locale, $this->sourceLocale());
    }

    public function categoryLabelForId(string $itemType, int $categoryId, ?string $locale): ?string
    {
        return $this->taxonomyFor($itemType)?->categoryLabel($categoryId, $locale, $this->sourceLocale());
    }

    /** @return list<string> */
    public function getTagLabels(string $itemType, int $itemId, ?string $locale): array
    {
        $taxonomy = $this->taxonomyFor($itemType);
        if ($taxonomy === null) {
            return [];
        }

        $labels = [];
        foreach ($taxonomy->tagTree()->leafmost($this->getTagIds($itemType, $itemId)) as $tagId) {
            $label = $taxonomy->tagLabel($tagId, $locale, $this->sourceLocale());
            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    public function categoryChoices(string $itemType, ?string $locale): array
    {
        return $this->choices($itemType, Axis::Category, $locale);
    }

    /**
     * @return array<int, string>
     */
    public function tagChoices(string $itemType, ?string $locale): array
    {
        return $this->choices($itemType, Axis::Tag, $locale);
    }

    /** @return list<array{label: ?string, levels: list<array{depth: int, offset: int, choices: array<int, string>}>}> */
    public function categoryChoiceGroups(string $itemType, ?string $locale): array
    {
        return $this->choiceGroups($itemType, Axis::Category, $locale);
    }

    /** @return list<array{label: ?string, levels: list<array{depth: int, offset: int, choices: array<int, string>}>}> */
    public function tagChoiceGroups(string $itemType, ?string $locale): array
    {
        return $this->choiceGroups($itemType, Axis::Tag, $locale);
    }

    /** @return array<int, string> */
    private function choices(string $itemType, Axis $axis, ?string $locale): array
    {
        $taxonomy = $this->enabledTaxonomy($itemType, $axis);
        if ($taxonomy === null) {
            return [];
        }

        $choices = [];
        foreach ($taxonomy->definitions($axis) as $definition) {
            $choices[$definition->id] = $definition->labelFor($locale, $this->sourceLocale());
        }

        return $choices;
    }

    /** @return list<array{label: ?string, levels: list<array{depth: int, offset: int, choices: array<int, string>}>}> */
    private function choiceGroups(string $itemType, Axis $axis, ?string $locale): array
    {
        $taxonomy = $this->enabledTaxonomy($itemType, $axis);
        if ($taxonomy === null) {
            return [];
        }

        $tree = $taxonomy->tagTree();

        $groups = [];
        $offset = 0;
        foreach ($taxonomy->groupedDefinitions($axis) as $bucket) {
            $levels = [];
            foreach ($bucket['definitions'] as $definition) {
                $depth = $axis === Axis::Tag ? $tree->depthOf($definition->id) : 1;
                $level = array_key_last($levels);
                if ($level === null || $levels[$level]['depth'] !== $depth) {
                    $levels[] = ['depth' => $depth, 'offset' => $offset, 'choices' => []];
                    $level = array_key_last($levels);
                }

                $levels[$level]['choices'][$definition->id] = $definition->labelFor($locale, $this->sourceLocale());
                $offset++;
            }

            $groups[] = [
                'label' => $bucket['group']?->labelFor($locale, $this->sourceLocale()),
                'levels' => $levels,
            ];
        }

        return $groups;
    }

    private function enabledTaxonomy(string $itemType, Axis $axis): ?Config
    {
        $provider = $this->registry->providerFor($itemType);
        $supported = $axis === Axis::Category ? $provider?->supportsCategories() : $provider?->supportsTags();
        if ($provider === null || $supported !== true) {
            return null;
        }

        $taxonomy = $provider->getTaxonomy();

        return $taxonomy->isEnabled($axis) ? $taxonomy : null;
    }

    private function taxonomyFor(string $itemType): ?Config
    {
        return $this->registry->providerFor($itemType)?->getTaxonomy();
    }

    private function sourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }
}
