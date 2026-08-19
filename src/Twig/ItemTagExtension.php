<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\Tag\ChangeTarget;
use App\Item\Tag\FacetSelection;
use App\Item\Tag\FacetService;
use App\Item\Tag\TagService;
use App\Item\ListRegistry;
use App\Item\Tag\TypeRegistry;
use App\Review\ChangeProposalService;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemTagExtension extends AbstractExtension
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly TypeRegistry $registry,
        private readonly RequestStack $requestStack,
        private readonly FacetService $facetService,
        private readonly ChangeProposalService $changeProposals,
        private readonly ListRegistry $listRegistry,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_tag_labels', $this->tagLabels(...)),
            new TwigFunction('item_tag_choices', $this->tagChoices(...)),
            new TwigFunction('item_tag_levels', $this->tagLevels(...)),
            new TwigFunction('item_tag_pending', $this->pendingCount(...)),
            new TwigFunction('item_facet_current', $this->currentFacets(...)),
            new TwigFunction('item_facet_active', $this->facetsActive(...)),
            new TwigFunction('item_detail_noindex', $this->detailNoindex(...)),
            new TwigFunction('item_facet_url', $this->facetUrl(...)),
            new TwigFunction('item_facet_counts', $this->facetCounts(...)),
        ];
    }

    public function detailNoindex(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        return is_string($route) && !$this->listRegistry->isDetailRouteIndexable($route);
    }

    public function currentFacets(): FacetSelection
    {
        return $this->facetService->current();
    }

    public function facetsActive(): bool
    {
        return !$this->facetService->current()->isEmpty();
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

    /** @return list<string> */
    public function tagLabels(string $itemType, int $itemId): array
    {
        return $this->tagService->getLabels($itemType, $itemId, $this->locale());
    }

    /** @return array<int, string> */
    public function tagChoices(string $itemType): array
    {
        return $this->tagService->getChoices($itemType, $this->locale());
    }

    /** @return list<array{depth: int, offset: int, choices: array<int, string>}> */
    public function tagLevels(string $itemType): array
    {
        return $this->tagService->getChoiceLevels($itemType, $this->locale());
    }

    public function pendingCount(string $itemType): int
    {
        if (!$this->registry->has($itemType)) {
            return 0;
        }

        return $this->changeProposals->countPendingForTargetType(ChangeTarget::TYPE_PREFIX . $itemType);
    }

    private function locale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
