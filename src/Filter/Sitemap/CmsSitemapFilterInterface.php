<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for sitemap CMS page ID filters.
 * Plugins can implement this to restrict which CMS pages appear in the sitemap.
 *
 * Multiple implementations are collected and applied sequentially.
 * Each filter receives the IDs that survived the previous filter.
 * If no filter is registered, all published CMS pages appear in the sitemap.
 */
#[AutoconfigureTag]
interface CmsSitemapFilterInterface
{
    /**
     * Filter the given list of CMS page IDs.
     *
     * @param array<int> $cmsIds IDs of currently allowed CMS pages
     * @return array<int> Filtered subset of CMS page IDs to include in the sitemap
     */
    public function filterCmsIds(array $cmsIds): array;
}
