<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemFacetExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_facet_current', [ItemFacetRuntime::class, 'currentFacets']),
            new TwigFunction('item_facet_url', [ItemFacetRuntime::class, 'facetUrl']),
            new TwigFunction('item_facet_counts', [ItemFacetRuntime::class, 'facetCounts']),
        ];
    }
}
