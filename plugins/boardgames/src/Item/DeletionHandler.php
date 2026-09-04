<?php declare(strict_types=1);

namespace Plugin\Boardgames\Item;

use App\Enum\ItemAction;
use App\Item\ActionInterface;
use Override;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Repository\GamePledgeRepository;
use Plugin\Boardgames\Service\GameService;

final readonly class DeletionHandler implements ActionInterface
{
    public function __construct(
        private GameOwnershipRepository $ownerships,
        private GamePledgeRepository $pledges,
        private BringRequestRepository $requests,
    ) {}

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted || $itemType !== GameService::ITEM_TYPE) {
            return;
        }

        $this->requests->deleteForGameIds([$itemId]);
        $this->pledges->deleteForGameIds([$itemId]);
        $this->ownerships->deleteForGameIds([$itemId]);
    }
}
