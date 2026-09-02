<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemTagExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_tag_labels', [ItemTagRuntime::class, 'tagLabels']),
            new TwigFunction('item_tag_choices', [ItemTagRuntime::class, 'tagChoices']),
            new TwigFunction('item_tag_levels', [ItemTagRuntime::class, 'tagLevels']),
            new TwigFunction('item_tag_pending', [ItemTagRuntime::class, 'pendingCount']),
            new TwigFunction('item_facet_current', [ItemTagRuntime::class, 'currentFacets']),
            new TwigFunction('item_facet_active', [ItemTagRuntime::class, 'facetsActive']),
            new TwigFunction('item_detail_noindex', [ItemTagRuntime::class, 'detailNoindex']),
            new TwigFunction('item_facet_url', [ItemTagRuntime::class, 'facetUrl']),
            new TwigFunction('item_facet_counts', [ItemTagRuntime::class, 'facetCounts']),
        ];
    }
}
