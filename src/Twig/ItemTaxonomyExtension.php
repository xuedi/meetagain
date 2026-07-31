<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ChangeTarget;
use App\Item\Taxonomy\FacetCounter;
use App\Item\Taxonomy\FacetCounts;
use App\Item\Taxonomy\FacetResolver;
use App\Item\Taxonomy\FacetSelection;
use App\Item\Taxonomy\ScopeCodec;
use App\Item\Taxonomy\TaxonomyService;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemTaxonomyExtension extends AbstractExtension
{
    public function __construct(
        private readonly TaxonomyService $taxonomyService,
        private readonly CategorizableTypeRegistry $registry,
        private readonly RequestStack $requestStack,
        private readonly FacetResolver $facetResolver,
        private readonly FacetCounter $facetCounter,
        private readonly ScopeCodec $scopeCodec,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_category_label', $this->categoryLabel(...)),
            new TwigFunction('item_category_label_by_id', $this->categoryLabelById(...)),
            new TwigFunction('item_tag_labels', $this->tagLabels(...)),
            new TwigFunction('item_taxonomy_category_choices', $this->categoryChoices(...)),
            new TwigFunction('item_taxonomy_tag_choices', $this->tagChoices(...)),
            new TwigFunction('item_taxonomy_category_groups', $this->categoryChoiceGroups(...)),
            new TwigFunction('item_taxonomy_tag_groups', $this->tagChoiceGroups(...)),
            new TwigFunction('item_taxonomy_target', $this->changeTarget(...)),
            new TwigFunction('item_facet_current', $this->currentFacets(...)),
            new TwigFunction('item_facet_active', $this->facetsActive(...)),
            new TwigFunction('item_facet_url', $this->facetUrl(...)),
            new TwigFunction('item_facet_counts', $this->facetCounts(...)),
        ];
    }

    public function currentFacets(): FacetSelection
    {
        return $this->facetResolver->current();
    }

    public function facetsActive(): bool
    {
        return !$this->facetResolver->current()->isEmpty();
    }

    public function facetUrl(string $baseUrl, FacetSelection $selection): string
    {
        return $this->facetResolver->urlFor($baseUrl, $selection);
    }

    public function facetCounts(string $itemType): ?FacetCounts
    {
        return $this->facetCounter->counts($itemType);
    }

    /** @return array{type: string, id: int}|null the change-proposal target of this type's vocabulary at the current scope */
    public function changeTarget(string $itemType): ?array
    {
        $targetId = $this->scopeCodec->currentTargetId();
        if ($targetId === null || !$this->registry->has($itemType)) {
            return null;
        }

        return ['type' => ChangeTarget::TYPE_PREFIX . $itemType, 'id' => $targetId];
    }

    public function categoryLabel(string $itemType, int $itemId): ?string
    {
        return $this->taxonomyService->getCategoryLabel($itemType, $itemId, $this->locale());
    }

    public function categoryLabelById(string $itemType, int|string|null $categoryId): ?string
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        return $this->taxonomyService->categoryLabelForId($itemType, (int) $categoryId, $this->locale());
    }

    /** @return list<string> */
    public function tagLabels(string $itemType, int $itemId): array
    {
        return $this->taxonomyService->getTagLabels($itemType, $itemId, $this->locale());
    }

    /** @return array<int, string> */
    public function categoryChoices(string $itemType): array
    {
        return $this->taxonomyService->categoryChoices($itemType, $this->locale());
    }

    /** @return array<int, string> */
    public function tagChoices(string $itemType): array
    {
        return $this->taxonomyService->tagChoices($itemType, $this->locale());
    }

    /** @return list<array{label: ?string, offset: int, choices: array<int, string>}> */
    public function categoryChoiceGroups(string $itemType): array
    {
        return $this->taxonomyService->categoryChoiceGroups($itemType, $this->locale());
    }

    /** @return list<array{label: ?string, offset: int, choices: array<int, string>}> */
    public function tagChoiceGroups(string $itemType): array
    {
        return $this->taxonomyService->tagChoiceGroups($itemType, $this->locale());
    }

    private function locale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
