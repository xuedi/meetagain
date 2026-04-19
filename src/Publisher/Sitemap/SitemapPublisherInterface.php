<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point for plugins (and core) to contribute URLs to the sitemap.
 *
 * Implementations must respect active tenant/language filters themselves;
 * `loc` must be an absolute URL. `SitemapService` merges results from all
 * publishers in priority order (higher first) with no further filtering.
 * A publisher may return an empty array to suppress its contribution on a
 * given request (e.g. whitelabel context).
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
