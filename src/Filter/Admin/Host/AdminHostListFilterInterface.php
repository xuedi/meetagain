<?php declare(strict_types=1);

namespace App\Filter\Admin\Host;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin host list filters.
 * Plugins can implement this to restrict which hosts appear in the admin list.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a host, it will be hidden from the admin list.
 */
#[AutoconfigureTag]
interface AdminHostListFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed host IDs for the admin list in the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all hosts)
     *         - array<int>: Only these host IDs are allowed
     *         - []: No hosts allowed (empty result)
     */
    public function getHostIdFilter(): ?array;

    /**
     * Check if a specific host is accessible in the admin context.
     *
     * @param int $hostId The host ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this host
     *         - false: Explicitly deny this host
     */
    public function isHostAccessible(int $hostId): ?bool;

    /**
     * Get debug context information for logging when access is denied.
     *
     * @param int $hostId The host ID being checked
     * @return array<string, mixed> Context information for logging (e.g., active filter, current context)
     */
    public function getDebugContext(int $hostId): array;
}
