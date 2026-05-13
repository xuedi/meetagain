<?php

declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use Plugin\Filmclub\Entity\ExternalSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class OmdbLookup implements FilmMetadataLookupInterface
{
    private const API_URL = 'https://www.omdbapi.com/';

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
            $params = ['s' => $query, 'apikey' => $this->apiKey, 'type' => 'movie'];
            if ($year !== null) {
                $params['y'] = $year;
            }

            $response = $this->httpClient->request('GET', self::API_URL, ['query' => $params]);
            $data = $response->toArray();

            if (($data['Response'] ?? 'False') !== 'True') {
                return [];
            }

            $results = [];
            foreach ($data['Search'] ?? [] as $item) {
                $results[] = new FilmMetadata(
                    externalId: $item['imdbID'] ?? '',
                    source: ExternalSource::Omdb,
                    title: $item['Title'] ?? '',
                    year: isset($item['Year']) ? (int) $item['Year'] : null,
                    posterUrl: ($item['Poster'] ?? 'N/A') !== 'N/A' ? $item['Poster'] : null,
                );
            }

            return $results;
        } catch (Throwable $e) {
            $this->logger->error('OMDb search failed: ' . $e->getMessage(), ['query' => $query]);

            return [];
        }
    }

    public function fetchById(string $externalId, string $locale): ?FilmMetadata
    {
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => ['i' => $externalId, 'apikey' => $this->apiKey, 'plot' => 'full'],
            ]);
            $data = $response->toArray();

            if (($data['Response'] ?? 'False') !== 'True') {
                return null;
            }

            $genres = array_filter(array_map(
                static fn(string $g) => strtolower(trim($g)),
                explode(',', $data['Genre'] ?? ''),
            ));

            return new FilmMetadata(
                externalId: $data['imdbID'] ?? $externalId,
                source: ExternalSource::Omdb,
                title: $data['Title'] ?? '',
                description: ($data['Plot'] ?? 'N/A') !== 'N/A' ? $data['Plot'] : null,
                year: isset($data['Year']) ? (int) $data['Year'] : null,
                runtime: $this->parseRuntime($data['Runtime'] ?? null),
                genres: array_values($genres),
                posterUrl: ($data['Poster'] ?? 'N/A') !== 'N/A' ? $data['Poster'] : null,
            );
        } catch (Throwable $e) {
            $this->logger->error('OMDb fetch failed: ' . $e->getMessage(), ['id' => $externalId]);

            return null;
        }
    }

    public function getSource(): ExternalSource
    {
        return ExternalSource::Omdb;
    }

    private function parseRuntime(?string $runtime): ?int
    {
        if ($runtime === null || $runtime === 'N/A') {
            return null;
        }
        $matches = [];
        if (preg_match('/(\d+)/', $runtime, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
