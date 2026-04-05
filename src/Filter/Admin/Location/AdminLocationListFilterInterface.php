<?php declare(strict_types=1);

namespace App\Filter\Admin\Location;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin location list filters.
 * Plugins can implement this to restrict which locations appear in the admin list.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a location, it will be hidden from the admin list.
 */
#[AutoconfigureTag]
interface AdminLocationListFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed location IDs for the admin list in the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all locations)
     *         - array<int>: Only these location IDs are allowed
     *         - []: No locations allowed (empty result)
     */
    public function getLocationIdFilter(): ?array;

    /**
     * Check if a specific location is accessible in the admin context.
     *
     * @param int $locationId The location ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this location
     *         - false: Explicitly deny this location
     */
    public function isLocationAccessible(int $locationId): ?bool;

    /**
     * Get debug context information for logging when access is denied.
     *
     * @param int $locationId The location ID being checked
     * @return array<string, mixed> Context information for logging (e.g., active filter, current context)
     */
    public function getDebugContext(int $locationId): array;
}
