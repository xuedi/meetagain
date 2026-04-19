<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for public routes that require no authentication.
 * These guard against regressions in SEO-critical and globally accessible endpoints.
 */
class PublicPagesTest extends WebTestCase
{
    public function testSitemapReturns200WithXmlContentType(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml');

        // Assert
        $this->assertResponseIsSuccessful('Sitemap should return HTTP 200');
        $this->assertResponseHeaderSame('content-type', 'application/xml; charset=UTF-8');
    }

    public function testSitemapContainsFlatUrlsetRoot(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml');
        $content = $client->getResponse()->getContent();

        // Assert
        static::assertStringContainsString('<?xml', $content, 'Sitemap should be valid XML');
        static::assertStringContainsString('<urlset', $content, 'Sitemap root should be a flat urlset');
        static::assertStringNotContainsString('<sitemapindex', $content, 'Flat sitemap must not emit a sitemap index');
        static::assertStringContainsString('<loc>', $content, 'Sitemap should contain at least one URL');
    }

    public function testRobotsTxtContainsAiCrawlerBlocksAndContentSignal(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/robots.txt');
        $content = $client->getResponse()->getContent();

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('User-agent: GPTBot', $content);
        static::assertStringContainsString('User-agent: ClaudeBot', $content);
        static::assertStringContainsString('User-agent: Google-Extended', $content);
        static::assertStringContainsString('Content-Signal: search=yes, ai-train=no, ai-input=no', $content);
        static::assertStringNotContainsString('Claude-Web', $content, 'Claude-Web is deprecated and must not appear');
        static::assertStringNotContainsString('anthropic-ai', $content, 'anthropic-ai is deprecated and must not appear');
    }

}
