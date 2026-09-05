<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Enum\LookupFailure;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class WikidataLookup implements GameMetadataLookupInterface
{
    private const string ENDPOINT = 'https://query.wikidata.org/sparql';

    private const int SEARCH_LIMIT = 20;

    private ?LookupFailure $lastFailure = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function searchByName(string $query): array
    {
        $rows = $this->query($this->buildSearchQuery($query));

        return array_values(array_map($this->mapRow(...), $rows));
    }

    public function fetchById(string $externalId): ?GameMetadata
    {
        $rows = $this->query($this->buildEntityQuery($externalId));
        if ($rows === []) {
            return null;
        }

        return $this->mapRow($rows[0]);
    }

    public function getSource(): ExternalSource
    {
        return ExternalSource::Wikidata;
    }

    public function getLastFailure(): ?LookupFailure
    {
        return $this->lastFailure;
    }

    /** @return list<array<string, array<string, string>>> */
    private function query(string $sparql): array
    {
        $this->lastFailure = null;

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'headers' => ['Accept' => 'application/sparql-results+json'],
                'query' => ['query' => $sparql],
            ]);

            if ($response->getStatusCode() === 429) {
                $this->lastFailure = LookupFailure::RateLimited;

                return [];
            }

            if ($response->getStatusCode() !== 200) {
                $this->lastFailure = LookupFailure::Unavailable;

                return [];
            }

            return $response->toArray()['results']['bindings'] ?? [];
        } catch (Throwable $exception) {
            $this->lastFailure = LookupFailure::Unavailable;
            $this->logger->error('Wikidata lookup failed: ' . $exception->getMessage());

            return [];
        }
    }

    /** @param array<string, array<string, string>> $row */
    private function mapRow(array $row): GameMetadata
    {
        $entityUri = $row['game']['value'] ?? '';
        $bggId = isset($row['bggId']) ? $row['bggId']['value'] : null;

        return new GameMetadata(
            externalId: substr($entityUri, (int) strrpos($entityUri, '/') + 1),
            source: ExternalSource::Wikidata,
            name: $row['gameLabel']['value'] ?? '',
            description: isset($row['description']) ? $row['description']['value'] : null,
            yearPublished: isset($row['year']) ? (int) substr($row['year']['value'], 0, 4) : null,
            minPlayers: isset($row['minPlayers']) ? (int) $row['minPlayers']['value'] : null,
            maxPlayers: isset($row['maxPlayers']) ? (int) $row['maxPlayers']['value'] : null,
            boxImageUrl: isset($row['image']) ? $row['image']['value'] : null,
            bggId: $bggId,
        );
    }

    private function buildSearchQuery(string $term): string
    {
        return sprintf(
            'SELECT ?game ?gameLabel ?year ?minPlayers ?maxPlayers ?image ?bggId WHERE {
  ?game wdt:P31/wdt:P279* wd:Q131436 .
  ?game rdfs:label ?gameLabel .
  FILTER(CONTAINS(LCASE(?gameLabel), "%s"))
  OPTIONAL { ?game wdt:P577 ?year. }
  OPTIONAL { ?game wdt:P1872 ?minPlayers. }
  OPTIONAL { ?game wdt:P1873 ?maxPlayers. }
  OPTIONAL { ?game wdt:P18 ?image. }
  OPTIONAL { ?game wdt:P2339 ?bggId. }
} LIMIT %d',
            $this->escape(mb_strtolower($term)),
            self::SEARCH_LIMIT,
        );
    }

    private function buildEntityQuery(string $entityId): string
    {
        return sprintf(
            'SELECT ?game ?gameLabel ?year ?minPlayers ?maxPlayers ?image ?bggId WHERE {
  BIND(wd:%s AS ?game)
  ?game rdfs:label ?gameLabel .
  FILTER(LANG(?gameLabel) = "en")
  OPTIONAL { ?game wdt:P577 ?year. }
  OPTIONAL { ?game wdt:P1872 ?minPlayers. }
  OPTIONAL { ?game wdt:P1873 ?maxPlayers. }
  OPTIONAL { ?game wdt:P18 ?image. }
  OPTIONAL { ?game wdt:P2339 ?bggId. }
} LIMIT 1',
            $this->escape($entityId),
        );
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\"', ' ', ' '], $value);
    }
}
