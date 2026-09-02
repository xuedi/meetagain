<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\Tag\FacetSelection;
use App\Item\Tag\FacetService;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ItemFacetRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private FacetService $facetService,
    ) {}

    public function currentFacets(): FacetSelection
    {
        return $this->facetService->current();
    }

    public function facetUrl(string $baseUrl, FacetSelection $selection): string
    {
        return $this->facetService->urlFor($baseUrl, $selection);
    }

    /** @return array{tags: array<int, int>, total: int, shown: int}|null */
    public function facetCounts(string $itemType): ?array
    {
        return $this->facetService->counts($itemType);
    }
}
