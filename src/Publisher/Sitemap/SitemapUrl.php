<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use DateTimeInterface;

/**
 * Value object describing a single `<url>` entry in the sitemap.
 *
 * Valid `changefreq` values per sitemaps.org: always, hourly, daily,
 * weekly, monthly, yearly, never. Kept as a string to avoid a dedicated
 * enum for a trivial vocabulary.
 *
 * `section`, `locale`, and `meta` are admin-UI metadata. They are NOT rendered
 * into the `<urlset>` XML; SitemapService::renderXml() ignores them entirely.
 * Their purpose is to let the admin sitemap page group, filter, and label rows
 * without re-parsing routes or re-querying the database.
 */
final readonly class SitemapUrl
{
    /**
     * @param array<string, string> $alternates locale => absolute URL, used to emit
     *                                          `<xhtml:link rel="alternate" hreflang="...">` entries
     * @param array<string, scalar> $meta       publisher-supplied entity metadata
     *                                          (`event_id`, `cms_id`, `group_id`, `group_name`, `title`)
     */
    public function __construct(
        public string $loc,
        public ?DateTimeInterface $lastmod = null,
        public ?string $changefreq = null,
        public ?float $priority = null,
        public array $alternates = [],
        public ?string $section = null,
        public ?string $locale = null,
        public array $meta = [],
    ) {}
}
