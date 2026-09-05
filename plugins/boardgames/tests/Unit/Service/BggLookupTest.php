<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Enum\LookupFailure;
use Plugin\Boardgames\Service\BggLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BggLookupTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../Fixtures';

    public function testSearchMapsEveryItem(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse($this->fixture('bgg-search.xml')));

        // Act
        $results = $lookup->searchByName('catan');

        // Assert
        static::assertCount(2, $results);
        static::assertSame('13', $results[0]->externalId);
        static::assertSame('CATAN', $results[0]->name);
        static::assertSame(1995, $results[0]->yearPublished);
        static::assertSame(ExternalSource::Bgg, $results[0]->source);
        static::assertNull($lookup->getLastFailure());
    }

    public function testFetchByIdMapsPlayerCountsPlaytimeAndWeight(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse($this->fixture('bgg-thing.xml')));

        // Act
        $metadata = $lookup->fetchById('13');

        // Assert
        static::assertNotNull($metadata);
        static::assertSame('CATAN', $metadata->name);
        static::assertSame('Die Siedler von Catan', $metadata->originalName);
        static::assertSame(3, $metadata->minPlayers);
        static::assertSame(4, $metadata->maxPlayers);
        static::assertSame(60, $metadata->minPlaytime);
        static::assertSame(120, $metadata->maxPlaytime);
        static::assertSame(10, $metadata->minAge);
        static::assertSame('2.30', $metadata->weight);
        static::assertSame('https://cf.geekdo-images.com/catan.jpg', $metadata->boxImageUrl);
    }

    public function testBestPlayerCountTakesThePollWinner(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse($this->fixture('bgg-thing.xml')));

        // Act
        $metadata = $lookup->fetchById('13');

        // Assert
        static::assertSame(4, $metadata?->bestPlayerCount);
    }

    public function testTheBearerTokenTravelsInTheAuthorizationHeader(): void
    {
        // Arrange
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'headers' => $options['headers'] ?? []];

            return new MockResponse('<items/>');
        });
        $lookup = new BggLookup($client, new NullLogger(), 'secret-token');

        // Act
        $lookup->searchByName('catan');

        // Assert
        static::assertStringStartsWith('https://boardgamegeek.com/xmlapi2/search', $seen['url']);
        static::assertContains('Authorization: Bearer secret-token', $seen['headers']);
    }

    public function testAnUnauthorizedResponseIsReportedRatherThanThrown(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse('', ['http_code' => 401]));

        // Act
        $results = $lookup->searchByName('catan');

        // Assert
        static::assertSame([], $results);
        static::assertSame(LookupFailure::Unauthorized, $lookup->getLastFailure());
    }

    public function testARateLimitedResponseIsReportedRatherThanThrown(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse('', ['http_code' => 429]));

        // Act
        $results = $lookup->searchByName('catan');

        // Assert
        static::assertSame([], $results);
        static::assertSame(LookupFailure::RateLimited, $lookup->getLastFailure());
    }

    public function testMalformedXmlIsReportedAsUnavailable(): void
    {
        // Arrange
        $lookup = $this->lookup(new MockResponse('<items><broken>'));

        // Act
        $metadata = $lookup->fetchById('13');

        // Assert
        static::assertNull($metadata);
        static::assertSame(LookupFailure::Unavailable, $lookup->getLastFailure());
    }

    private function lookup(MockResponse $response): BggLookup
    {
        return new BggLookup(new MockHttpClient($response), new NullLogger(), 'token');
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(self::FIXTURE_DIR . '/' . $name);
    }
}
