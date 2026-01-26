<?php declare(strict_types=1);

namespace App\Service\CmsFilter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for CMS page content filters.
 * Plugins can implement this to restrict which CMS pages are visible.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a CMS page, it will be hidden.
 */
#[AutoconfigureTag]
interface CmsFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed CMS page IDs for the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all CMS pages)
     *         - array<int>: Only these CMS page IDs are allowed
     *         - []: No CMS pages allowed (empty result)
     */
    public function getCmsIdFilter(): ?array;

    /**
     * Check if a specific CMS page is accessible in the current context.
     *
     * @param int $cmsId The CMS page ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this CMS page
     *         - false: Explicitly deny this CMS page
     */
    public function isCmsAccessible(int $cmsId): ?bool;
}
