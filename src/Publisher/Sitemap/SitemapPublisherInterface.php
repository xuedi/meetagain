<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes URLs to the sitemap. Implementations must apply active filters themselves and
 * `loc` must be absolute; results are merged in priority order.
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
