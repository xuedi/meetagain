<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ItemListSidebarTest extends WebTestCase
{
    private const string GLOSSARY_HOST = 'dragon.meetagain.local';
    private const string FILM_HOST = 'cinema.meetagain.local';

    /** @return iterable<string, array{string, string, string}> */
    public static function listPageProvider(): iterable
    {
        yield 'glossary' => [self::GLOSSARY_HOST, '/en/glossary', 'glossary'];
        yield 'films' => [self::FILM_HOST, '/en/films', 'film'];
    }

    #[DataProvider('listPageProvider')]
    public function testSidebarRendersBesideTheList(string $host, string $url, string $itemType): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', $url, server: ['HTTP_HOST' => $host]);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertCount(1, $crawler->filter('.item-list-layout > .item-list-sidebar'));
        static::assertCount(1, $crawler->filter('.item-list-layout > .item-list-main'));
        static::assertCount(
            1,
            $crawler->filter('.item-list-sidebar a[href$="/item/' . $itemType . '/view/list"]'),
            'The view switcher belongs to the sidebar',
        );
        static::assertStringContainsString(
            'item-list-sidebar',
            (string) $crawler->filter('.item-list-layout > .column')->first()->attr('class'),
            'The sidebar comes first in the DOM so it stacks above the list on narrow viewports',
        );
    }

    #[DataProvider('listPageProvider')]
    public function testResultHeaderStatesTheListSizeInsideTheSwappedRegion(string $host, string $url, string $itemType): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', $url, server: ['HTTP_HOST' => $host]);

        // Assert
        $rows = $crawler->filter('[data-item-list-scope="' . $itemType . '"] .item-list tbody tr')->count();
        static::assertGreaterThan(0, $rows);
        static::assertStringContainsString(
            (string) $rows,
            $crawler->filter('[data-item-list-body] .item-result-header')->text(),
        );
    }

    public function testViewSwitcherIsTheFirstSidebarBox(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        $boxes = $crawler->filter('.item-list-sidebar .box');
        static::assertCount(
            1,
            $boxes->first()->filter('a[href$="/item/glossary/view/list"]'),
            'Box order is view -> filter -> about',
        );
        static::assertStringContainsString('item-tag-filter', (string) $boxes->eq(1)->attr('class'));
    }

    public function testEveryFacetOptionIsAChipAndAnEmptyOneIsNotClickable(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        $links = $crawler->filter('[data-item-filter] a.tag[data-item-facet]');
        static::assertGreaterThan(0, $links->count());
        static::assertSame('nofollow', $links->first()->attr('rel'));
        static::assertGreaterThan(
            0,
            $crawler->filter('[data-item-filter] span.tag')->count(),
            'An option that would yield nothing renders as a dimmed span, never a link',
        );
    }

    public function testASubTagRendersIndentedBelowItsParent(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        $rows = $crawler->filter('[data-item-facet-axis="tag"] > .tags');
        static::assertGreaterThan(1, $rows->count(), 'A nested vocabulary renders one row per level');
        static::assertStringNotContainsString('padding-left', (string) $rows->first()->attr('style'));
        static::assertStringContainsString('padding-left', (string) $rows->eq(1)->attr('style'));
    }

    public function testAFacetedPageNarrowsTheCountAndIsNotIndexed(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);
        $total = $crawler->filter('.item-list tbody tr')->count();
        $chip = $crawler->filter('[data-item-filter] a.tag[data-item-facet]')->first();

        // Act
        $faceted = $client->request('GET', (string) $chip->attr('href'), server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        $this->assertResponseIsSuccessful();
        $narrowed = $faceted->filter('.item-list tbody tr')->count();
        static::assertLessThan($total, $narrowed);
        static::assertStringContainsString(
            (string) $total,
            $faceted->filter('.item-result-header')->text(),
            'The header states the narrowed count against the unfaceted total',
        );
        static::assertCount(
            1,
            $faceted->filter('meta[name="robots"][content="noindex,follow"]'),
        );
        static::assertGreaterThan(
            0,
            $faceted->filter('.item-result-header a[data-item-facet]')->count(),
            'The active facet is repeated as a removable chip',
        );
    }

    public function testAnUnfacetedPageStaysIndexable(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        static::assertCount(0, $crawler->filter('meta[name="robots"]'));
    }

    public function testGlossarySidebarOffersOnlyItsTwoModes(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary', server: ['HTTP_HOST' => self::GLOSSARY_HOST]);

        // Assert
        static::assertCount(2, $crawler->filter('.item-list-sidebar a[href*="/item/glossary/view/"]'));
    }
}
