<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The sidebar is pure Twig, so its only coverage is the rendered page. The four list pages have
 * deliberately different shapes - shared list component, own per-mode layouts, restricted mode set -
 * which is what makes them worth asserting together.
 */
class ItemListSidebarTest extends WebTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function listPageProvider(): iterable
    {
        yield 'films' => ['/en/films', 'film'];
        yield 'books' => ['/en/books', 'book'];
        yield 'dishes' => ['/en/dishes', 'dish'];
        yield 'glossary' => ['/en/glossary', 'glossary'];
    }

    #[DataProvider('listPageProvider')]
    public function testSidebarRendersBesideTheList(string $url, string $itemType): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', $url);

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

    public function testAboutBoxCarriesTheEntryCount(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        $rows = $crawler->filter('.item-list tbody tr')->count();
        static::assertGreaterThan(0, $rows);
        static::assertStringContainsString(
            (string) $rows,
            $crawler->filter('.item-list-sidebar .box')->last()->text(),
        );
    }

    public function testGlossarySidebarOffersOnlyItsTwoModes(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/glossary');

        // Assert
        static::assertCount(2, $crawler->filter('.item-list-sidebar a[href*="/item/glossary/view/"]'));
    }
}
