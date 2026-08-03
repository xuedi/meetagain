<?php declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class EventLocaleVisibilityTest extends WebTestCase
{
    #[DataProvider('provideLocales')]
    public function testFeaturedPageNeverRendersAnUntitledCard(string $locale): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/' . $locale . '/event/featured/');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertNotContains('', $this->texts($crawler, '.card-content h2'));
    }

    #[DataProvider('provideLocales')]
    public function testEventListNeverRendersAnUntitledEvent(string $locale): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/' . $locale . '/events');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertNotContains('', $this->texts($crawler, 'a.title.is-4'));
    }

    #[DataProvider('provideLocales')]
    public function testTheFeaturedButtonAppearsExactlyWhenTheLocaleHasFeaturedEvents(string $locale): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $featuredPage = $client->request('GET', '/' . $locale . '/event/featured/');
        $featured = $featuredPage->filter('div.columns.is-multiline')->first()->filter('.card-content h2')->count();
        $list = $client->request('GET', '/' . $locale . '/events');
        $buttons = $list->filter('a[href$="/event/featured/"]')->count();

        // Assert
        static::assertSame($featured > 0, $buttons > 0);
    }

    /**
     * @return list<string>
     */
    private function texts(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn(Crawler $node): string => trim($node->text()));
    }

    public static function provideLocales(): iterable
    {
        yield 'spanish' => ['es'];
        yield 'french' => ['fr'];
        yield 'chinese' => ['zh'];
        yield 'english' => ['en'];
    }
}
