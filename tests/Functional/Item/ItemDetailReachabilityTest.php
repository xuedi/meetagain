<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ItemDetailReachabilityTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function detailPageProvider(): iterable
    {
        yield 'films' => ['cinema.meetagain.local', '/en/films', '/en/films/'];
        yield 'glossary' => ['dragon.meetagain.local', '/en/glossary', '/en/glossary/'];
    }

    #[DataProvider('detailPageProvider')]
    public function testTheListLinksToADetailPageTheSameHostServes(string $host, string $listPath, string $detailPrefix): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', $listPath, server: ['HTTP_HOST' => $host]);
        $links = $crawler->filter('a[href^="' . $detailPrefix . '"]');
        static::assertGreaterThan(0, $links->count(), "The list at {$listPath} links to no detail page");

        // Act
        $client->request('GET', (string) $links->first()->attr('href'), server: ['HTTP_HOST' => $host]);

        // Assert
        $this->assertResponseIsSuccessful();
    }
}
