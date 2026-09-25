<?php declare(strict_types=1);

namespace App\Item\Report;

use App\Entity\User;
use App\Enum\ItemAction;
use App\Item\ActionInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class DeletionHandler implements ActionInterface
{
    public function __construct(
        private ReportService $reportService,
        private Security $security,
    ) {}

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted) {
            return;
        }

        $user = $this->security->getUser();
        $this->reportService->resolveRemovedItem($itemType, $itemId, $user instanceof User ? $user : null);
    }
}
