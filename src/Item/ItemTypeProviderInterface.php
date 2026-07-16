<?php declare(strict_types=1);

namespace App\Item;

use App\Entity\EventItemAssociation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one item type with the core item seam. The registry keys providers by
 * getKey() and shows only those whose owning plugin is active.
 */
#[AutoconfigureTag]
interface ItemTypeProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type; the value stored in EventItemAssociation::itemType. */
    public function getKey(): string;

    /** Translation key for the type's label. */
    public function getLabelKey(): string;

    /** Cell rendered on the event detail page for one association; null when the item no longer exists. */
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string;

    /** Per-item cell markup the shared list component wraps in the chosen layout; null when the item is gone. */
    public function renderListCell(int $itemId): ?string;

    /** Steward search/pick fragment rendered inside the attach control. */
    public function renderAttachPicker(int $eventId): string;

    /** Order in the attach type dropdown (ascending). */
    public function getPriority(): int;
}
