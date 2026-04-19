<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects URLs from all registered publishers and renders a single flat
 * `<urlset>` sitemap document.
 */
final readonly class SitemapService
{
    /**
     * @param iterable<SitemapPublisherInterface> $publishers
     */
    public function __construct(
        #[AutowireIterator(SitemapPublisherInterface::class)]
        private iterable $publishers,
    ) {}

    /**
     * @return array<SitemapUrl>
     */
    public function getUrls(): array
    {
        $publishers = iterator_to_array($this->publishers, false);

        usort(
            $publishers,
            static fn(
                SitemapPublisherInterface $a,
                SitemapPublisherInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        $urls = [];
        foreach ($publishers as $publisher) {
            foreach ($publisher->getSitemapUrls() as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    public function renderXml(): string
    {
        $urls = $this->getUrls();
        $hasAlternates = false;
        foreach ($urls as $url) {
            if ($url->alternates === []) {
                continue;
            }

            $hasAlternates = true;
            break;
        }

        $xmlns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($hasAlternates) {
            $xmlns .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset ' . $xmlns . '>';

        foreach ($urls as $url) {
            $lines[] = $this->renderUrl($url);
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }

    private function renderUrl(SitemapUrl $url): string
    {
        $parts = ['  <url>'];
        $parts[] = '    <loc>' . self::escape($url->loc) . '</loc>';

        if ($url->lastmod !== null) {
            $parts[] = '    <lastmod>' . $url->lastmod->format('Y-m-d') . '</lastmod>';
        }

        if ($url->changefreq !== null) {
            $parts[] = '    <changefreq>' . self::escape($url->changefreq) . '</changefreq>';
        }

        if ($url->priority !== null) {
            $parts[] = '    <priority>' . number_format($url->priority, 1, '.', '') . '</priority>';
        }

        foreach ($url->alternates as $locale => $href) {
            $parts[] = sprintf(
                '    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>',
                self::escape($locale),
                self::escape($href),
            );
        }

        $parts[] = '  </url>';

        return implode("\n", $parts);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
