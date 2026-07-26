<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use DateTimeInterface;

final readonly class SitemapUrl
{
    /**
     * @param array<string, string> $alternates locale => absolute URL, emitted as hreflang alternates
     * @param array<string, scalar> $meta publisher metadata: event_id, cms_id, group_id, group_name, title
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
