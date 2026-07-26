<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Item\ItemFilterInterface;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

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
