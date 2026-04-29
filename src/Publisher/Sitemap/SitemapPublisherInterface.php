<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point for plugins (and core) to contribute URLs to the sitemap.
 *
 * Implementations must apply any active filters themselves; `loc` must be absolute.
 * SitemapService merges results from all publishers in priority order (higher first).
 * A publisher may return an empty array to suppress its contribution on a request.
 */
#[AutoconfigureTag]
interface SitemapPublisherInterface
{
    /**
     * Higher priority runs first. Default convention: core = 0, plugins = 10.
     */
    public function getPriority(): int;

    /**
     * @return array<SitemapUrl>
     */
    public function getSitemapUrls(): array;
}
