<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\Taxonomy\ItemTaxonomyService;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig bridge for item taxonomy display: the assigned category label and tag labels for one item,
 * resolved in the current request's locale with source-locale fallback.
 */
final class ItemTaxonomyExtension extends AbstractExtension
{
    public function __construct(
        private readonly ItemTaxonomyService $taxonomyService,
        private readonly RequestStack $requestStack,
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
            new TwigFunction('item_taxonomy_current_category', $this->currentCategory(...)),
            new TwigFunction('item_taxonomy_current_tags', $this->currentTags(...)),
            new TwigFunction('item_taxonomy_tag_toggle_url', $this->tagToggleUrl(...)),
        ];
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

    public function currentCategory(): ?int
    {
        $value = $this->requestStack->getCurrentRequest()?->query->get('category');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /** @return list<int> */
    public function currentTags(): array
    {
        $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
        $raw = $query['tag'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    /** The current-request URL with the given tag toggled in/out of the tag[] facet. */
    public function tagToggleUrl(int $tagId): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $query = $request->query->all();
        $tags = $this->currentTags();
        if (in_array($tagId, $tags, true)) {
            $tags = array_values(array_filter($tags, static fn(int $id): bool => $id !== $tagId));
        } else {
            $tags[] = $tagId;
        }

        if ($tags === []) {
            unset($query['tag']);
        } else {
            $query['tag'] = $tags;
        }

        $queryString = http_build_query($query);

        return $request->getPathInfo() . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function locale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
