<?php declare(strict_types=1);

namespace App\Filter\Plugin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for filtering the active plugin list based on request context.
 * Plugins can implement this to restrict which plugins are considered active
 * for the current request context.
 *
 * Multiple filters can be registered — they are composed with AND logic.
 * If any filter restricts the set, the intersection is taken.
 */
#[AutoconfigureTag]
interface PluginListFilterInterface
{
    /**
     * Filter the list of globally active plugin keys for the current request context.
     *
     * @param array<string> $activeKeys Plugin keys that are enabled in config/plugins.php
     * @return array<string>|null Returns:
     *         - null: No filtering for this context (allow all globally active plugins)
     *         - array<string>: Restrict to these plugin keys (service intersects with $activeKeys)
     */
    public function filterActivePlugins(array $activeKeys): ?array;
}
