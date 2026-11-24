<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Service;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Films\Service\TmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TmdbLookupTest extends TestCase
{
    #[DataProvider('nonNumericIds')]
    public function testANonNumericIdMakesNoRequest(string $externalId): void
    {
        // Arrange
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;

            return new MockResponse('{"id": 550, "title": "Fight Club"}');
        });
        $lookup = new TmdbLookup($client, new NullLogger(), 'tmdb-key');

        // Act
        $film = $lookup->fetchById($externalId, 'en');

        // Assert
        static::assertNull($film);
        static::assertSame([], $requested);
    }

    public static function nonNumericIds(): Generator
    {
        yield 'path to another endpoint' => ['550/credits'];
        yield 'parent path' => ['../account'];
        yield 'query string' => ['550?append_to_response=videos'];
        yield 'empty' => [''];
    }
}
