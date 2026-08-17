<?php declare(strict_types=1);

namespace App\Item;

use App\Enum\ItemViewType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Renders one item's cell for the shared item-list component. The registry keys implementations
 * by getKey() and shows only those whose owning plugin is active. Independent of
 * TypeProviderInterface: a type that only lists implements this seam alone.
 */
#[AutoconfigureTag]
interface ListCellProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type. */
    public function getKey(): string;

    /**
     * Per-item cell markup the shared list component wraps in the chosen layout; null when the item is gone.
     * A non-null mode overrides the visitor's session view mode, for callers rendering outside that list.
     */
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string;
}
