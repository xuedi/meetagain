<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Enum\ItemAction;
use App\Item\ActionInterface;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Override;

final readonly class AssignmentCleanupHandler implements ActionInterface
{
    public function __construct(
        private ItemCategoryAssignmentRepository $categoryRepo,
        private ItemTagAssignmentRepository $tagRepo,
    ) {}

    #[Override]
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void
    {
        if ($action !== ItemAction::Deleted) {
            return;
        }

        $this->categoryRepo->deleteFor($itemType, $itemId);
        $this->tagRepo->deleteFor($itemType, $itemId);
    }
}
