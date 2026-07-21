<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Entity\ItemCategoryAssignment;
use App\Entity\ItemTagAssignment;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read/write item taxonomy assignments keyed by (itemType, itemId), and resolve display labels
 * from the scope-resolved definitions. Writes validate the id against the current definitions and
 * silently drop unknown ids rather than throw.
 */
readonly class ItemTaxonomyService
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

            $wanted[] = $tagId;
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

    /** Label for an arbitrary category id (not necessarily assigned), e.g. a pending suggestion. */
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
        foreach ($this->getTagIds($itemType, $itemId) as $tagId) {
            $label = $taxonomy->tagLabel($tagId, $locale, $this->sourceLocale());
            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Category options for the list filter, id => label, in the given locale. Empty when the type
     * does not support/enable categories.
     *
     * @return array<int, string>
     */
    public function categoryChoices(string $itemType, ?string $locale): array
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null || !$provider->supportsCategories()) {
            return [];
        }

        $taxonomy = $provider->getTaxonomy();
        if (!$taxonomy->isCategoriesEnabled()) {
            return [];
        }

        $choices = [];
        foreach ($taxonomy->categoryDefinitions() as $definition) {
            $choices[$definition->id] = $definition->labelFor($locale, $this->sourceLocale());
        }

        return $choices;
    }

    /**
     * Tag options for the list filter, id => label, in the given locale. Empty when the type does
     * not support/enable tags.
     *
     * @return array<int, string>
     */
    public function tagChoices(string $itemType, ?string $locale): array
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null || !$provider->supportsTags()) {
            return [];
        }

        $taxonomy = $provider->getTaxonomy();
        if (!$taxonomy->isTagsEnabled()) {
            return [];
        }

        $choices = [];
        foreach ($taxonomy->tagDefinitions() as $definition) {
            $choices[$definition->id] = $definition->labelFor($locale, $this->sourceLocale());
        }

        return $choices;
    }

    private function taxonomyFor(string $itemType): ?TaxonomyConfig
    {
        return $this->registry->providerFor($itemType)?->getTaxonomy();
    }

    private function sourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }
}
