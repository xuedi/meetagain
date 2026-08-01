<?php declare(strict_types=1);

namespace App\Item\Tag;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the tags of one item type a visitor may see. Implementations compose with
 * AND-intersection; null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface FilterInterface
{
    /** @return list<int>|null */
    public function getAllowedTagIds(string $itemType): ?array;
}
