<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Item\ItemFilterInterface;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Narrows an item list by the request's taxonomy facets: ?category=<id> is a single equality,
 * ?tag[]=<id> is AND (an item must carry every selected tag), and the two facets AND together.
 * This composes with the rest of the ItemFilterInterface chain by AND-intersection, so any external
 * visibility filter still applies. Returns null (no opinion) when no facet is present for the type,
 * or the type is not categorizable - staying inert on unfiltered lists and event-cell rendering.
 */
final readonly class TaxonomyItemFilter implements ItemFilterInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private CategorizableTypeRegistry $registry,
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
    ) {}

    #[Override]
    public function getAllowedItemIds(string $itemType): ?array
    {
        $provider = $this->registry->providerFor($itemType);
        $request = $this->requestStack->getCurrentRequest();
        if ($provider === null || $request === null) {
            return null;
        }

        $result = null;

        $categoryRaw = $request->query->get('category');
        if ($provider->supportsCategories() && $categoryRaw !== null && $categoryRaw !== '') {
            $result = $this->categoryRepo->itemIdsWithCategory($itemType, (int) $categoryRaw);
        }

        $tagIds = $this->readTagIds($request->query->all());
        if ($provider->supportsTags() && $tagIds !== []) {
            $tagAllowed = $this->tagRepo->itemIdsWithAllTags($itemType, $tagIds);
            $result = $result === null ? $tagAllowed : array_values(array_intersect($result, $tagAllowed));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $query
     * @return list<int>
     */
    private function readTagIds(array $query): array
    {
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
}
