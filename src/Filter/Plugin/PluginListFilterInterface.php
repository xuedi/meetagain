<?php declare(strict_types=1);

namespace App\Filter\Plugin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the plugins active for the current request. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface PluginListFilterInterface
{
    /**
     * @param array<string> $activeKeys
     * @return array<string>|null null = no opinion, [key, ...] = allow-list intersected with $activeKeys
     */
    public function filterActivePlugins(array $activeKeys): ?array;
}
