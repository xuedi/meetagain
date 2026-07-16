<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes extra attach actions to the core attach control for a given event and item
 * type. Union chain: the attach control renders every provider's slots.
 */
#[AutoconfigureTag]
interface ItemAttachSlotProviderInterface
{
    /** @return list<ItemAttachSlot> */
    public function getAttachSlots(int $eventId, string $itemType): array;
}
