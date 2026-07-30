<?php declare(strict_types=1);

namespace Tests\Functional\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BreadcrumbTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function itemDetailProvider(): iterable
    {
        yield 'films' => ['cinema.meetagain.local', '/en/films', '/en/films/', 'Films'];
        yield 'glossary' => ['dragon.meetagain.local', '/en/glossary', '/en/glossary/', 'Glossary'];
    }

    #[DataProvider('itemDetailProvider')]
    public function testItemDetailPageCarriesABreadcrumbTrail(string $host, string $listPath, string $detailPrefix, string $sectionLabel): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', $listPath, server: ['HTTP_HOST' => $host]);
        $detailPath = (string) $crawler->filter('a[href^="' . $detailPrefix . '"]')->first()->attr('href');

        // Act
        $client->request('GET', $detailPath, server: ['HTTP_HOST' => $host]);

        // Assert
        $schema = $this->breadcrumbSchema($client);
        static::assertSame('BreadcrumbList', $schema['@type']);
        static::assertCount(3, $schema['itemListElement']);
        static::assertSame('Homepage', $schema['itemListElement'][0]['name']);
        static::assertSame($sectionLabel, $schema['itemListElement'][1]['name']);
        static::assertArrayNotHasKey('item', $schema['itemListElement'][2]);
    }

    public function testEventDetailBreadcrumbLabelsAreLocalised(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', '/de/events');
        $hrefs = $crawler->filter('a[href^="/de/event/"]')->each(static fn($node): string => (string) $node->attr('href'));
        $detailPaths = array_values(array_filter($hrefs, static fn(string $href): bool => preg_match('#^/de/event/\d+$#', $href) === 1));
        static::assertNotEmpty($detailPaths, 'The event list links to no event detail page');

        // Act
        $client->request('GET', $detailPaths[0]);

        // Assert
        $schema = $this->breadcrumbSchema($client);
        static::assertSame('Startseite', $schema['itemListElement'][0]['name']);
        static::assertSame('Events', $schema['itemListElement'][1]['name']);
    }

    /** @return array<string, mixed> */
    private function breadcrumbSchema(KernelBrowser $client): array
    {
        $this->assertResponseIsSuccessful();
        $blocks = [];
        foreach ($client->getCrawler()->filter('script[type="application/ld+json"]') as $node) {
            $decoded = json_decode((string) $node->textContent, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && ($decoded['@type'] ?? null) === 'BreadcrumbList') {
                $blocks[] = $decoded;
            }
        }

        static::assertCount(1, $blocks, 'Expected exactly one BreadcrumbList block');

        return $blocks[0];
    }
}
