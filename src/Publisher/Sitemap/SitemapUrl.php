<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use DateTimeInterface;

/**
 * Value object describing a single `<url>` entry in the sitemap.
 *
 * Valid `changefreq` values per sitemaps.org: always, hourly, daily,
 * weekly, monthly, yearly, never. Kept as a string to avoid a dedicated
 * enum for a trivial vocabulary.
 */
final readonly class SitemapUrl
{
    /**
     * @param array<string, string> $alternates locale => absolute URL, used to emit
     *                                          `<xhtml:link rel="alternate" hreflang="...">` entries
     */
    public function __construct(
        public string $loc,
        public ?DateTimeInterface $lastmod = null,
        public ?string $changefreq = null,
        public ?float $priority = null,
        public array $alternates = [],
    ) {}
}
