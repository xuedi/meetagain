<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use App\Publisher\Sitemap\SitemapUrl;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Keeps a published sitemap row out of the feed; a row is listed only when every filter lists it.
 */
#[AutoconfigureTag]
interface SitemapUrlFilterInterface
{
    public function isListed(SitemapUrl $url): bool;
}
