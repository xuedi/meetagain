<?php declare(strict_types=1);

namespace App\Service\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the events a plugin's demo seeder may attach its items to. Implementations compose with
 * AND-intersection; null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface SeedEventScopeInterface
{
    /** @return int[]|null */
    public function allowedEventIds(string $pluginKey): ?array;
}
