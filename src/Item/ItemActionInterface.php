<?php declare(strict_types=1);

namespace App\Item;

use App\Enum\ItemAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reacts to an item's lifecycle, carrying the item type so one handler serves every type.
 * Called after flush, when the item row has an id.
 */
#[AutoconfigureTag]
interface ItemActionInterface
{
    public function onItemAction(ItemAction $action, string $itemType, int $itemId): void;
}
