<?php declare(strict_types=1);

namespace App\Item;

use App\Item\Tag\FacetService;
use App\Publisher\Noindex\NoindexProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class NoindexProvider implements NoindexProviderInterface
{
    public function __construct(
        private FacetService $facetService,
        private ListRegistry $listRegistry,
    ) {}

    public function shouldNoindex(Request $request): bool
    {
        $route = $request->attributes->get('_route');
        $onNonIndexableDetailPage = is_string($route) && !$this->listRegistry->isDetailRouteIndexable($route);

        return $onNonIndexableDetailPage || !$this->facetService->current()->isEmpty();
    }
}
