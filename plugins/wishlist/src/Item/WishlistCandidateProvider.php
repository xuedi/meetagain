<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\Item\ItemCandidateProviderInterface;
use Override;
use Plugin\Wishlist\Service\WishlistService;

/**
 * Feeds the backlog's most-wanted items, ranked by demand, into the core candidate chain that
 * the voting poll-create UX and the attach control read.
 */
final readonly class WishlistCandidateProvider implements ItemCandidateProviderInterface
{
    public function __construct(
        private WishlistService $wishlistService,
    ) {}

    #[Override]
    public function getCandidateItemIds(string $itemType): array
    {
        return $this->wishlistService->getCandidateItemIds($itemType);
    }
}
