<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Enum\LookupFailure;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class BggLookup implements GameMetadataLookupInterface
{
    private const string BASE_URL = 'https://boardgamegeek.com/xmlapi2';

    private const int SEARCH_LIMIT = 20;

    private ?LookupFailure $lastFailure = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[SensitiveParameter]
        private readonly string $token,
    ) {}

    public function searchByName(string $query): array
    {
        $xml = $this->request('/search', ['query' => $query, 'type' => 'boardgame']);
        if ($xml === null) {
            return [];
        }

        $results = [];
        foreach ($xml->item as $item) {
            $results[] = new GameMetadata(
                externalId: (string) $item['id'],
                source: ExternalSource::Bgg,
                name: (string) ($item->name['value'] ?? ''),
                yearPublished: $this->intOrNull((string) ($item->yearpublished['value'] ?? '')),
                bggId: (string) $item['id'],
            );

            if (count($results) >= self::SEARCH_LIMIT) {
                break;
            }
        }

        return $results;
    }

    public function fetchById(string $externalId): ?GameMetadata
    {
        $xml = $this->request('/thing', ['id' => $externalId, 'stats' => '1']);
        if ($xml === null || !isset($xml->item)) {
            return null;
        }

        return $this->mapThing($xml->item[0]);
    }

    public function getSource(): ExternalSource
    {
        return ExternalSource::Bgg;
    }

    public function getLastFailure(): ?LookupFailure
    {
        return $this->lastFailure;
    }

    /** @param array<string, string> $query */
    private function request(string $path, array $query): ?SimpleXMLElement
    {
        $this->lastFailure = null;

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $this->token],
                'query' => $query,
            ]);

            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                $this->lastFailure = LookupFailure::Unauthorized;
                $this->logger->warning('BGG lookup rejected the configured token.', ['status' => $status]);

                return null;
            }

            if ($status === 429) {
                $this->lastFailure = LookupFailure::RateLimited;
                $this->logger->warning('BGG lookup was rate limited.');

                return null;
            }

            if ($status !== 200) {
                $this->lastFailure = LookupFailure::Unavailable;
                $this->logger->warning('BGG lookup returned an unexpected status.', ['status' => $status]);

                return null;
            }

            return $this->parse($response->getContent());
        } catch (Throwable $exception) {
            $this->lastFailure = LookupFailure::Unavailable;
            $this->logger->error('BGG lookup failed: ' . $exception->getMessage(), ['path' => $path]);

            return null;
        }
    }

    private function parse(string $body): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            $this->lastFailure = LookupFailure::Unavailable;

            return null;
        }

        return $xml;
    }

    private function mapThing(SimpleXMLElement $item): GameMetadata
    {
        $names = [];
        foreach ($item->name as $name) {
            $names[(string) $name['type']] = (string) $name['value'];
        }

        return new GameMetadata(
            externalId: (string) $item['id'],
            source: ExternalSource::Bgg,
            name: $names['primary'] ?? reset($names) ?: '',
            originalName: $names['alternate'] ?? null,
            description: $this->stringOrNull(html_entity_decode((string) $item->description, ENT_QUOTES | ENT_HTML5)),
            yearPublished: $this->attributeInt($item, 'yearpublished'),
            minPlayers: $this->attributeInt($item, 'minplayers'),
            maxPlayers: $this->attributeInt($item, 'maxplayers'),
            bestPlayerCount: $this->bestPlayerCount($item),
            minPlaytime: $this->attributeInt($item, 'minplaytime'),
            maxPlaytime: $this->attributeInt($item, 'maxplaytime'),
            minAge: $this->attributeInt($item, 'minage'),
            weight: $this->weight($item),
            boxImageUrl: $this->stringOrNull((string) $item->image),
            bggId: (string) $item['id'],
        );
    }

    private function bestPlayerCount(SimpleXMLElement $item): ?int
    {
        $bestCount = null;
        $bestVotes = 0;

        foreach ($item->poll as $poll) {
            if ((string) $poll['name'] !== 'suggested_numplayers') {
                continue;
            }

            foreach ($poll->results as $results) {
                foreach ($results->result as $result) {
                    if ((string) $result['value'] !== 'Best') {
                        continue;
                    }

                    $votes = (int) $result['numvotes'];
                    $players = $this->intOrNull((string) $results['numplayers']);
                    if ($players !== null && $votes > $bestVotes) {
                        $bestVotes = $votes;
                        $bestCount = $players;
                    }
                }
            }
        }

        return $bestCount;
    }

    private function weight(SimpleXMLElement $item): ?string
    {
        $raw = (string) ($item->statistics->ratings->averageweight['value'] ?? '');
        if ($raw === '' || (float) $raw <= 0.0) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function attributeInt(SimpleXMLElement $item, string $tag): ?int
    {
        return $this->intOrNull((string) ($item->{$tag}['value'] ?? ''));
    }

    private function intOrNull(string $value): ?int
    {
        return $value === '' || (int) $value === 0 ? null : (int) $value;
    }

    private function stringOrNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
