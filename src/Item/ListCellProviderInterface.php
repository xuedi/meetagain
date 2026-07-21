<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Renders one item's cell for the shared item-list component. The registry keys implementations
 * by getKey() and shows only those whose owning plugin is active. Independent of
 * ItemTypeProviderInterface: a type that only lists implements this seam alone.
 */
#[AutoconfigureTag]
interface ListCellProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type. */
    public function getKey(): string;

    /** Per-item cell markup the shared list component wraps in the chosen layout; null when the item is gone. */
    public function renderListCell(int $itemId): ?string;
}
