<?php declare(strict_types=1);

namespace App\Filter\Admin\Cms;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin CMS list filters.
 * Plugins can implement this to restrict which CMS pages appear in the admin list.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a CMS page, it will be hidden from the admin list.
 */
#[AutoconfigureTag]
interface AdminCmsListFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed CMS page IDs for the admin list in the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all CMS pages)
     *         - array<int>: Only these CMS page IDs are allowed
     *         - []: No CMS pages allowed (empty result)
     */
    public function getCmsIdFilter(): ?array;

    /**
     * Check if a specific CMS page is accessible in the admin context.
     *
     * @param int $cmsId The CMS page ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this CMS page
     *         - false: Explicitly deny this CMS page
     */
    public function isCmsAccessible(int $cmsId): ?bool;

    /**
     * Get debug context information for logging when access is denied.
     *
     * @param int $cmsId The CMS page ID being checked
     * @return array<string, mixed> Context information for logging (e.g., current group, domain)
     */
    public function getDebugContext(int $cmsId): array;
}
