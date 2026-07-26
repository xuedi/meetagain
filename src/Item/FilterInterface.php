<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the visible item ids of one type. Implementations compose with AND-intersection;
 * null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface FilterInterface
{
    /** @return int[]|null */
    public function getAllowedItemIds(string $itemType): ?array;
}
