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
        $this->assertResponseHeaderSame('content-type', 'text/xml; charset=UTF-8');
    }

    public function testSitemapContainsExpectedXmlStructure(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml');
        $content = $client->getResponse()->getContent();

        // Assert
        static::assertStringContainsString('<?xml', $content, 'Sitemap should be valid XML');
        static::assertStringContainsString('<sitemapindex', $content, 'Sitemap root should be a sitemap index');
        static::assertStringContainsString('<loc>', $content, 'Sitemap index should contain at least one sitemap URL');
    }
}
