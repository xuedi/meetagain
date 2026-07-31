<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class ItemFacetTreeTest extends WebTestCase
{
    private const string HOST = 'cinema.meetagain.local';
    private const string PAGE = '/en/films';

    public function testASubTagRendersInItsOwnIndentedRowBelowItsParent(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);

        // Assert
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('[data-item-facet-axis="tag"] > .tags');
        static::assertCount(3, $rows, 'The root, the branch below it, then the remaining roots');
        static::assertStringNotContainsString('padding-left', (string) $rows->first()->attr('style'));
        static::assertStringContainsString('padding-left', (string) $rows->eq(1)->attr('style'));
        static::assertSame(['Drama4'], $this->chipTexts($rows->first()));
        static::assertSame(['Family drama2', 'Road movie1'], $this->chipTexts($rows->eq(1)));
    }

    public function testAParentFacetMatchesEveryEntryTaggedWithADescendant(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);

        // Act
        $faceted = $client->request('GET', $this->chipHref($crawler, 'Drama'), server: ['HTTP_HOST' => self::HOST]);

        // Assert
        static::assertCount(4, $faceted->filter('.item-list tbody tr'));
        static::assertStringNotContainsString(
            'The Zone of Interest',
            $faceted->filter('.item-list tbody')->text(),
            'A film outside the branch drops out',
        );
    }

    public function testASubTagFacetNarrowsToThatBranchAlone(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::PAGE, server: ['HTTP_HOST' => self::HOST]);

        // Act
        $faceted = $client->request('GET', $this->chipHref($crawler, 'Road movie'), server: ['HTTP_HOST' => self::HOST]);

        // Assert
        static::assertCount(1, $faceted->filter('.item-list tbody tr'));
        static::assertStringContainsString('Drive My Car', $faceted->filter('.item-list tbody')->text());
    }

    private function chipHref(Crawler $crawler, string $label): string
    {
        $chip = $crawler->filter('[data-item-facet-axis="tag"] a.tag')->reduce(
            static fn(Crawler $node): bool => str_starts_with($node->text(), $label),
        );

        return (string) $chip->first()->attr('href');
    }

    /** @return list<string> */
    private function chipTexts(Crawler $row): array
    {
        return $row->filter('.tag')->each(static fn(Crawler $chip): string => $chip->text());
    }
}
