<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use Plugin\Films\Entity\ExternalSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class TmdbLookup implements FilmMetadataLookupInterface
{
    private const BASE_URL = 'https://api.themoviedb.org/3';
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {}

    /** @return FilmMetadata[] */
    public function searchByTitle(string $query, ?int $year, string $locale): array
    {
        try {
            $params = [
                'query' => $query,
                'language' => $locale,
                'include_adult' => 'false',
            ];
            if ($year !== null) {
                $params['year'] = $year;
            }

            $response = $this->httpClient->request('GET', self::BASE_URL . '/search/movie', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'query' => $params,
            ]);

            $results = [];
            foreach ($response->toArray()['results'] ?? [] as $item) {
                $results[] = $this->mapSearchResult($item);
            }

            return $results;
        } catch (Throwable $e) {
            $this->logger->error('TMDB search failed: ' . $e->getMessage(), ['query' => $query]);

            return [];
        }
    }

    public function fetchById(string $externalId, string $locale): ?FilmMetadata
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/movie/' . $externalId, [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'query' => ['language' => $locale],
            ]);

            return $this->mapDetail($response->toArray());
        } catch (Throwable $e) {
            $this->logger->error('TMDB fetch failed: ' . $e->getMessage(), ['id' => $externalId]);

            return null;
        }
    }

    public function getSource(): ExternalSource
    {
        return ExternalSource::Tmdb;
    }

    private function mapSearchResult(array $item): FilmMetadata
    {
        return new FilmMetadata(
            externalId: (string) $item['id'],
            source: ExternalSource::Tmdb,
            title: $item['title'] ?? '',
            originalTitle: $item['original_title'] ?? null,
            description: $item['overview'] ?? null,
            year: isset($item['release_date']) && $item['release_date'] !== '' ? (int) substr($item['release_date'], 0, 4) : null,
            posterUrl: isset($item['poster_path']) ? self::IMAGE_BASE . $item['poster_path'] : null,
        );
    }

    private function mapDetail(array $item): FilmMetadata
    {
        $genres = array_filter(array_map(static fn(array $g) => strtolower($g['name'] ?? ''), $item['genres'] ?? []));

        return new FilmMetadata(
            externalId: (string) $item['id'],
            source: ExternalSource::Tmdb,
            title: $item['title'] ?? '',
            originalTitle: $item['original_title'] ?? null,
            description: $item['overview'] ?? null,
            year: isset($item['release_date']) && $item['release_date'] !== '' ? (int) substr($item['release_date'], 0, 4) : null,
            runtime: isset($item['runtime']) && $item['runtime'] > 0 ? $item['runtime'] : null,
            genres: array_values($genres),
            posterUrl: isset($item['poster_path']) ? self::IMAGE_BASE . $item['poster_path'] : null,
        );
    }
}
