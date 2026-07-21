<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Enum\ItemAction;
use App\Item\ItemActionInterface;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use Override;

/**
 * Sweeps taxonomy assignment rows when an item is deleted. The assignment tables carry no FK to
 * any item table, so orphan rows are removed here instead of by a cascade.
 */
final readonly class TaxonomyAssignmentCleanupHandler implements ItemActionInterface
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
