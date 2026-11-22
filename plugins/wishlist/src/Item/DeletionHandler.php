<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\Enum\ItemAction;
use App\Item\ActionInterface;
use Override;
use Plugin\Wishlist\Repository\WishlistEntryRepository;

final readonly class DeletionHandler implements ActionInterface
{
    public function __construct(
        private WishlistEntryRepository $entries,
    ) {}

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted) {
            return;
        }

        $this->entries->deleteByItem($itemType, $itemId);
    }
}
