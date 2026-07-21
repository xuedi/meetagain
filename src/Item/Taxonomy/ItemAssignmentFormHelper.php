<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds and reads the unmapped category/tag assignment fields on an item's steward edit form.
 * Options come from the type's scope-resolved definitions, labelled in the admin's UI locale;
 * assignment is a selection (an id), not translated content, so there is no per-language editing.
 * Fields are added only for the features the type enables and supports. Pair with
 * ItemTaxonomyService to persist what extractAssignment() returns.
 */
readonly class ItemAssignmentFormHelper
{
    public const string CATEGORY_FIELD = 'taxonomyCategory';
    public const string TAGS_FIELD = 'taxonomyTags';

    public function __construct(
        private CategorizableTypeRegistry $registry,
        private ItemTaxonomyService $taxonomyService,
        private LanguageService $languageService,
        private RequestStack $requestStack,
    ) {}

    public function addAssignmentFields(FormBuilderInterface $builder, string $typeKey, ?int $itemId): void
    {
        $provider = $this->registry->providerFor($typeKey);
        if ($provider === null) {
            return;
        }

        $taxonomy = $provider->getTaxonomy();
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        if ($provider->supportsCategories() && $taxonomy->isCategoriesEnabled()) {
            $builder->add(self::CATEGORY_FIELD, ChoiceType::class, [
                'label' => 'item.taxonomy_category_label',
                'choices' => $taxonomy->categoryOptions($locale, $sourceLocale),
                'placeholder' => 'item.taxonomy_category_none',
                'required' => false,
                'mapped' => false,
                'data' => $itemId !== null ? $this->taxonomyService->getCategory($typeKey, $itemId) : null,
            ]);
        }

        if ($provider->supportsTags() && $taxonomy->isTagsEnabled()) {
            $builder->add(self::TAGS_FIELD, ChoiceType::class, [
                'label' => 'item.taxonomy_tags_label',
                'choices' => $taxonomy->tagOptions($locale, $sourceLocale),
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'data' => $itemId !== null ? $this->taxonomyService->getTagIds($typeKey, $itemId) : [],
            ]);
        }
    }

    /** @return array{category: ?int, tags: list<int>} */
    public function extractAssignment(FormInterface $form): array
    {
        $category = $form->has(self::CATEGORY_FIELD) ? $form->get(self::CATEGORY_FIELD)->getData() : null;
        $tags = $form->has(self::TAGS_FIELD) ? ($form->get(self::TAGS_FIELD)->getData() ?? []) : [];

        return [
            'category' => $category !== null ? (int) $category : null,
            'tags' => array_map('intval', array_values($tags)),
        ];
    }
}
