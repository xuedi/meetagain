<?php declare(strict_types=1);

namespace Plugin\Wishlist\Item;

use App\EntityActionInterface;
use App\Enum\EntityAction;
use App\Repository\EventItemAssociationRepository;
use Override;
use Plugin\Wishlist\Service\WishlistService;

/**
 * Ages the backlog whenever any item is attached to an event - whether a vote committed its
 * outcome or a steward attached it directly, both flow through the core association service.
 * Reacts only to the neutral EntityAction signal, so wishlist never depends on voting.
 */
final readonly class WishlistSelectionHandler implements EntityActionInterface
{
    public function __construct(
        private EventItemAssociationRepository $associationRepo,
        private WishlistService $wishlistService,
    ) {}

    #[Override]
    public function onEntityAction(EntityAction $action, int $entityId): void
    {
        if ($action !== EntityAction::CreateEventItemAssociation) {
            return;
        }

        $association = $this->associationRepo->find($entityId);
        if ($association === null) {
            return;
        }

        $this->wishlistService->onSelectionOutcome((string) $association->getItemType(), (int) $association->getItemId());
    }
}
