<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapUrl;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SitemapUrlTest extends TestCase
{
    public function testConstructsWithAllFields(): void
    {
        // Arrange
        $lastmod = new DateTimeImmutable('2026-04-19');

        // Act
        $url = new SitemapUrl(
            loc: 'https://example.com/page',
            lastmod: $lastmod,
            changefreq: 'daily',
            priority: 0.8,
            alternates: ['en' => 'https://example.com/en/page', 'de' => 'https://example.com/de/page'],
        );

        // Assert
        self::assertSame('https://example.com/page', $url->loc);
        self::assertSame($lastmod, $url->lastmod);
        self::assertSame('daily', $url->changefreq);
        self::assertSame(0.8, $url->priority);
        self::assertSame(['en' => 'https://example.com/en/page', 'de' => 'https://example.com/de/page'], $url->alternates);
    }

    public function testDefaultsAreNullOrEmpty(): void
    {
        // Act
        $url = new SitemapUrl(loc: 'https://example.com/');

        // Assert
        self::assertNull($url->lastmod);
        self::assertNull($url->changefreq);
        self::assertNull($url->priority);
        self::assertSame([], $url->alternates);
    }
}
