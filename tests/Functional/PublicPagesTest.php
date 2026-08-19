<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testSitemapListsEveryLocOnlyOnce(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml');
        $locs = $this->sitemapLocs($client);

        // Assert
        static::assertNotEmpty($locs);
        static::assertSame([], array_values(array_diff_assoc($locs, array_unique($locs))), 'A loc is advertised twice');
    }

    public function testSitemapAdvertisesTheListAndDetailPagesOfAnActiveItemType(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml', server: ['HTTP_HOST' => 'cinema.meetagain.local']);
        $locs = $this->sitemapLocs($client);

        // Assert
        static::assertNotEmpty(preg_grep('#/en/films$#', $locs), 'The film list page is missing');
        static::assertNotEmpty(preg_grep('#/en/films/\d+$#', $locs), 'No film entry page is advertised');
    }

    public function testSitemapAdvertisesTheListButNotTheDetailPagesOfATypeThatOptsOut(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/sitemap.xml', server: ['HTTP_HOST' => 'dragon.meetagain.local']);
        $locs = $this->sitemapLocs($client);

        // Assert
        static::assertNotEmpty(preg_grep('#/en/glossary$#', $locs), 'The glossary list page must stay indexable');
        static::assertSame([], preg_grep('#/en/glossary/\d+$#', $locs), 'Glossary entry pages must not be advertised');
    }

    public function testADetailPageOfATypeThatOptsOutIsNoindexWhileItsListPageIsNot(): void
    {
        // Arrange
        $client = static::createClient();
        $host = ['HTTP_HOST' => 'dragon.meetagain.local'];

        // Act
        $client->request('GET', '/en/glossary', server: $host);
        $list = (string) $client->getResponse()->getContent();
        $crawler = $client->request('GET', '/en/glossary', server: $host);
        $firstEntry = $crawler->filter('a[href*="/en/glossary/"]')->first()->attr('href');
        $client->request('GET', (string) $firstEntry, server: $host);
        $detail = (string) $client->getResponse()->getContent();

        // Assert
        static::assertStringNotContainsString('name="robots"', $list, 'The list page must stay indexable');
        static::assertStringContainsString('<meta name="robots" content="noindex,follow">', $detail);
    }

    public function testRobotsTxtDisallowsApiPathsAndAdvertisesSitemap(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/robots.txt');
        $content = $client->getResponse()->getContent();

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('User-agent: *', $content);
        static::assertStringContainsString('Disallow: /api/v1/', $content);
        static::assertStringNotContainsString('/api/openapi.', $content);
        static::assertStringNotContainsString('Disallow: /api/schema', $content);
        static::assertStringNotContainsString("Disallow: /api/\n", $content);
        static::assertMatchesRegularExpression('#Sitemap: https?://[^/]+/sitemap\.xml#', $content);
    }

    /** @return list<string> */
    private function sitemapLocs(KernelBrowser $client): array
    {
        $this->assertResponseIsSuccessful();
        preg_match_all('#<loc>([^<]+)</loc>#', (string) $client->getResponse()->getContent(), $matches);

        return $matches[1];
    }
}
