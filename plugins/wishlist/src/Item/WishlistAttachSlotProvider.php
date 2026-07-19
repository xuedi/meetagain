<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\Item\ItemAttachSlot;
use App\Item\ItemAttachSlotProviderInterface;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contributes the "pick from wishlist" action to the core attach control, linking to the
 * ranked-backlog picker scoped to that event and item type.
 */
final readonly class WishlistAttachSlotProvider implements ItemAttachSlotProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getAttachSlots(int $eventId, string $itemType): array
    {
        return [
            new ItemAttachSlot(
                url: $this->urlGenerator->generate('app_wishlist_pick', ['eventId' => $eventId, 'itemType' => $itemType]),
                labelKey: 'wishlist_attach.pick_from_wishlist',
                icon: 'heart',
            ),
        ];
    }
}
