<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Service\OmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OmdbLookupTest extends TestCase
{
    public function testSearchByTitleReturnsMappedResults(): void
    {
        // Arrange
        $payload = json_encode([
            'Response' => 'True',
            'Search' => [
                [
                    'imdbID' => 'tt1375666',
                    'Title' => 'Inception',
                    'Year' => '2010',
                    'Poster' => 'https://example.com/poster.jpg',
                ],
            ],
        ]);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new OmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $results = $lookup->searchByTitle('Inception', null, 'en');

        // Assert
        static::assertCount(1, $results);
        static::assertSame('tt1375666', $results[0]->externalId);
        static::assertSame('Inception', $results[0]->title);
        static::assertSame(2010, $results[0]->year);
        static::assertSame(ExternalSource::Omdb, $results[0]->source);
        static::assertSame('https://example.com/poster.jpg', $results[0]->posterUrl);
    }

    public function testSearchByTitleReturnsEmptyWhenResponseFalse(): void
    {
        // Arrange
        $payload = json_encode(['Response' => 'False', 'Error' => 'Movie not found!']);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new OmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $results = $lookup->searchByTitle('nonexistent', null, 'en');

        // Assert
        static::assertSame([], $results);
    }

    public function testSearchByTitleReturnsEmptyOnHttpError(): void
    {
        // Arrange
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);
        $lookup = new OmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $results = $lookup->searchByTitle('anything', null, 'en');

        // Assert
        static::assertSame([], $results);
    }

    public function testFetchByIdReturnsMappedDetail(): void
    {
        // Arrange
        $payload = json_encode([
            'Response' => 'True',
            'imdbID' => 'tt1375666',
            'Title' => 'Inception',
            'Year' => '2010',
            'Runtime' => '148 min',
            'Genre' => 'Action, Adventure, Sci-Fi',
            'Plot' => 'A thief who steals corporate secrets.',
            'Poster' => 'https://example.com/poster.jpg',
        ]);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new OmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $result = $lookup->fetchById('tt1375666', 'en');

        // Assert
        static::assertNotNull($result);
        static::assertSame('tt1375666', $result->externalId);
        static::assertSame(148, $result->runtime);
        static::assertContains('action', $result->genres);
        static::assertContains('sci-fi', $result->genres);
        static::assertSame(ExternalSource::Omdb, $result->source);
    }

    public function testFetchByIdReturnsNullWhenResponseFalse(): void
    {
        // Arrange
        $payload = json_encode(['Response' => 'False', 'Error' => 'Incorrect IMDb ID.']);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new OmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $result = $lookup->fetchById('tt0000000', 'en');

        // Assert
        static::assertNull($result);
    }

    public function testGetSourceReturnsOmdb(): void
    {
        // Arrange
        $lookup = new OmdbLookup(new MockHttpClient(), new NullLogger(), 'key');

        // Act / Assert
        static::assertSame(ExternalSource::Omdb, $lookup->getSource());
    }
}
