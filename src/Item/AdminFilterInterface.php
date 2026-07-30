<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the item ids a manager may read or change, independently of which host is being served.
 * Implementations compose with AND-intersection; null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface AdminFilterInterface
{
    /** @return int[]|null */
    public function getAllowedItemIds(string $itemType): ?array;
}
