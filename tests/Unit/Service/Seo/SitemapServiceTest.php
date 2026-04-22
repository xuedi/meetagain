<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Seo\SitemapService;
use DateTimeImmutable;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class SitemapServiceTest extends TestCase
{
    public function testMergesPublishersInPriorityOrder(): void
    {
        // Arrange: two publishers, high priority first.
        $low = $this->makePublisher(priority: 0, urls: [new SitemapUrl(loc: 'https://example.com/low')]);
        $high = $this->makePublisher(priority: 10, urls: [new SitemapUrl(loc: 'https://example.com/high')]);

        $service = new SitemapService([$low, $high]);

        // Act
        $urls = $service->getUrls();

        // Assert
        self::assertSame('https://example.com/high', $urls[0]->loc);
        self::assertSame('https://example.com/low', $urls[1]->loc);
    }

    public function testMergesMultiplePluginPublishersInPriorityOrder(): void
    {
        // Arrange: simulate the real shape - core (priority 0) + multiple plugin publishers (priority 10).
        $core = $this->makePublisher(priority: 0, urls: [new SitemapUrl(loc: 'https://example.com/core')]);
        $pluginA = $this->makePublisher(priority: 10, urls: [new SitemapUrl(loc: 'https://example.com/plugin-a')]);
        $pluginB = $this->makePublisher(priority: 10, urls: [new SitemapUrl(loc: 'https://example.com/plugin-b')]);

        $service = new SitemapService([$core, $pluginA, $pluginB]);

        // Act
        $urls = $service->getUrls();

        // Assert: plugin URLs come first (any order between them), core last.
        self::assertCount(3, $urls);
        self::assertSame('https://example.com/core', end($urls)->loc);
        $pluginLocs = [$urls[0]->loc, $urls[1]->loc];
        self::assertContains('https://example.com/plugin-a', $pluginLocs);
        self::assertContains('https://example.com/plugin-b', $pluginLocs);
    }

    public function testRenderXmlProducesWellFormedUrlsetWithAlternates(): void
    {
        // Arrange
        $service = new SitemapService([
            $this->makePublisher(priority: 0, urls: [
                new SitemapUrl(
                    loc: 'https://example.com/page',
                    lastmod: new DateTimeImmutable('2026-04-19'),
                    changefreq: 'daily',
                    priority: 0.8,
                    alternates: ['en' => 'https://example.com/en/page', 'de' => 'https://example.com/de/page'],
                ),
            ]),
        ]);

        // Act
        $xml = $service->renderXml();

        // Assert
        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($xml), 'Output should parse as well-formed XML');
        self::assertSame('urlset', $doc->documentElement?->nodeName);
        self::assertStringContainsString('<loc>https://example.com/page</loc>', $xml);
        self::assertStringContainsString('<lastmod>2026-04-19</lastmod>', $xml);
        self::assertStringContainsString('<changefreq>daily</changefreq>', $xml);
        self::assertStringContainsString('<priority>0.8</priority>', $xml);
        self::assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="https://example.com/en/page"/>', $xml);
        self::assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
    }

    public function testRenderXmlEscapesAmpersandInUrls(): void
    {
        // Arrange
        $service = new SitemapService([
            $this->makePublisher(priority: 0, urls: [
                new SitemapUrl(loc: 'https://example.com/search?q=a&b=c'),
            ]),
        ]);

        // Act
        $xml = $service->renderXml();

        // Assert: well-formed and & is escaped.
        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($xml));
        self::assertStringContainsString('&amp;', $xml);
        self::assertStringNotContainsString('?q=a&b=c', $xml);
    }

    public function testOmitsXhtmlNamespaceWhenNoAlternates(): void
    {
        // Arrange
        $service = new SitemapService([
            $this->makePublisher(priority: 0, urls: [new SitemapUrl(loc: 'https://example.com/')]),
        ]);

        // Act
        $xml = $service->renderXml();

        // Assert
        self::assertStringNotContainsString('xmlns:xhtml', $xml);
    }

    /**
     * @param array<SitemapUrl> $urls
     */
    private function makePublisher(int $priority, array $urls): SitemapPublisherInterface
    {
        return new class($priority, $urls) implements SitemapPublisherInterface {
            /**
             * @param array<SitemapUrl> $urls
             */
            public function __construct(
                private readonly int $priority,
                private readonly array $urls,
            ) {}

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function getSitemapUrls(): array
            {
                return $this->urls;
            }
        };
    }
}
