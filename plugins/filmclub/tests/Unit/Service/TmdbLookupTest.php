<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Service\TmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TmdbLookupTest extends TestCase
{
    public function testSearchByTitleReturnsMappedResults(): void
    {
        // Arrange
        $payload = json_encode([
            'results' => [
                [
                    'id' => 123,
                    'title' => 'Inception',
                    'original_title' => 'Inception',
                    'overview' => 'A thief who steals corporate secrets.',
                    'release_date' => '2010-07-16',
                    'poster_path' => '/poster.jpg',
                ],
            ],
        ]);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new TmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $results = $lookup->searchByTitle('Inception', null, 'en');

        // Assert
        static::assertCount(1, $results);
        static::assertSame('123', $results[0]->externalId);
        static::assertSame('Inception', $results[0]->title);
        static::assertSame(2010, $results[0]->year);
        static::assertSame(ExternalSource::Tmdb, $results[0]->source);
        static::assertStringContainsString('/poster.jpg', $results[0]->posterUrl ?? '');
    }

    public function testSearchByTitleReturnsEmptyOnHttpError(): void
    {
        // Arrange
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);
        $lookup = new TmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $results = $lookup->searchByTitle('anything', null, 'en');

        // Assert
        static::assertSame([], $results);
    }

    public function testFetchByIdReturnsMappedDetail(): void
    {
        // Arrange
        $payload = json_encode([
            'id' => 27205,
            'title' => 'Inception',
            'original_title' => 'Inception',
            'overview' => 'A thief who steals corporate secrets.',
            'release_date' => '2010-07-16',
            'runtime' => 148,
            'genres' => [
                ['id' => 28, 'name' => 'Action'],
                ['id' => 12, 'name' => 'Adventure'],
            ],
            'poster_path' => '/poster.jpg',
        ]);
        $http = new MockHttpClient([new MockResponse($payload)]);
        $lookup = new TmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $result = $lookup->fetchById('27205', 'en');

        // Assert
        static::assertNotNull($result);
        static::assertSame('27205', $result->externalId);
        static::assertSame(148, $result->runtime);
        static::assertContains('action', $result->genres);
        static::assertContains('adventure', $result->genres);
        static::assertSame(ExternalSource::Tmdb, $result->source);
    }

    public function testFetchByIdReturnsNullOnHttpError(): void
    {
        // Arrange
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $lookup = new TmdbLookup($http, new NullLogger(), 'test-api-key');

        // Act
        $result = $lookup->fetchById('bad-id', 'en');

        // Assert
        static::assertNull($result);
    }

    public function testGetSourceReturnsTmdb(): void
    {
        // Arrange
        $lookup = new TmdbLookup(new MockHttpClient(), new NullLogger(), 'key');

        // Act / Assert
        static::assertSame(ExternalSource::Tmdb, $lookup->getSource());
    }
}
