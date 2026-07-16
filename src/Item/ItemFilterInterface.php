<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Restricts which item ids of a given type are visible in the current request.
 * Multiple implementations compose with AND logic.
 *
 * Conventions:
 *   null  = no opinion; bypasses filtering (no implementation registered)
 *   []    = block all
 *   int[] = restrict to these ids
 */
#[AutoconfigureTag]
interface ItemFilterInterface
{
    /** @return int[]|null */
    public function getAllowedItemIds(string $itemType): ?array;
}
