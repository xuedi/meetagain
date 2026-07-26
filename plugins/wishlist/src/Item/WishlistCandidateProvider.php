<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\Item\CandidateProviderInterface;
use Override;
use Plugin\Wishlist\Service\WishlistService;

final readonly class WishlistCandidateProvider implements CandidateProviderInterface
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
