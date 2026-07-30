<?php declare(strict_types=1);

namespace App\Item;

use App\Entity\EventItemAssociation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one item type as attachable to events. The registry keys providers by
 * getKey() and shows only those whose owning plugin is active.
 */
#[AutoconfigureTag]
interface TypeProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type; the value stored in EventItemAssociation::itemType. */
    public function getKey(): string;

    /** Translation key for the type's label. */
    public function getLabelKey(): string;

    /**
     * Cell rendered on the event detail page for one association; null when the item no longer exists.
     * The association is the authorization: an attached item is part of the event, so read it without
     * the visibility narrowing that gates the type's own list and detail pages.
     */
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string;

    /**
     * Steward search/pick fragment rendered inside the attach control. Attaching is a management
     * action, so list what the steward may manage rather than what the serving host displays.
     */
    public function renderAttachPicker(int $eventId): string;

    /** Order in the attach type dropdown (ascending). */
    public function getPriority(): int;
}
