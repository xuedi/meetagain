<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Enum\LookupFailure;
use Plugin\Boardgames\Service\WikidataLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WikidataLookupTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../Fixtures';

    public function testSearchMapsBindingsIncludingTheBggId(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse($this->fixture('wikidata-search.json')));

        // Act
        $results = $lookup->searchByName('carcassonne');

        // Assert
        static::assertCount(1, $results);
        static::assertSame('Q170436', $results[0]->externalId);
        static::assertSame('Carcassonne', $results[0]->name);
        static::assertSame(2000, $results[0]->yearPublished);
        static::assertSame(2, $results[0]->minPlayers);
        static::assertSame(5, $results[0]->maxPlayers);
        static::assertSame('822', $results[0]->bggId);
        static::assertSame(ExternalSource::Wikidata, $results[0]->source);
    }

    public function testFetchByIdReturnsNullWhenNothingBinds(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse('{"results":{"bindings":[]}}'));

        // Act
        $metadata = $lookup->fetchById('Q1');

        // Assert
        static::assertNull($metadata);
    }

    public function testAnErrorResponseIsReportedRatherThanThrown(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse('', ['http_code' => 500]));

        // Act
        $results = $lookup->searchByName('carcassonne');

        // Assert
        static::assertSame([], $results);
        static::assertSame(LookupFailure::Unavailable, $lookup->getLastFailure());
    }

    public function testQuotesInTheSearchTermCannotBreakOutOfTheSparqlLiteral(): void
    {
        // Arrange
        $seen = '';
        $client = new MockHttpClient(static function (string $method, string $url) use (&$seen): MockResponse {
            $seen = $url;

            return new MockResponse('{"results":{"bindings":[]}}');
        });
        $lookup = new WikidataLookup($client, new NullLogger());

        // Act
        $lookup->searchByName('a" } UNION { ?x ?y ?z');

        // Assert
        static::assertStringNotContainsString('%22+%7D+UNION', $seen);
        static::assertStringContainsString('%5C%22', $seen);
    }

    private function lookup(MockResponse $response): WikidataLookup
    {
        return new WikidataLookup(new MockHttpClient($response), new NullLogger());
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(self::FIXTURE_DIR . '/' . $name);
    }
}
