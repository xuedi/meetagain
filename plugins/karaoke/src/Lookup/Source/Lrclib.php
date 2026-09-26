<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup\Source;

use Override;
use Plugin\Karaoke\Lookup\Candidate;
use Plugin\Karaoke\Lookup\JsonClient;
use Plugin\Karaoke\Lookup\LyricsSourceInterface;
use Plugin\Karaoke\ValueObject\Config;

readonly class Lrclib implements LyricsSourceInterface
{
    public const string KEY = 'lrclib';

    private const string BASE_URL = 'https://lrclib.net/api';
    private const int SEARCH_LIMIT = 20;

    public function __construct(
        private JsonClient $client,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function isEnabled(Config $config): bool
    {
        return $config->isLrclibEnabled();
    }

    #[Override]
    public function getPriority(): int
    {
        return 100;
    }

    #[Override]
    public function search(string $title, ?string $artist): array
    {
        $candidates = [];
        if ($artist !== null && $artist !== '') {
            $candidates = $this->searchBy(['track_name' => $title, 'artist_name' => $artist]);
        }

        return $candidates !== [] ? $candidates : $this->searchBy(['q' => $title]);
    }

    #[Override]
    public function fetch(string $externalId): ?Candidate
    {
        if (preg_match('~^\d{1,12}$~', $externalId) !== 1) {
            return null;
        }

        $entry = $this->client->get(self::BASE_URL . '/get/' . $externalId);

        return $entry === null ? null : $this->candidate($entry);
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<Candidate>
     */
    private function searchBy(array $query): array
    {
        $candidates = [];
        foreach ($this->client->get(self::BASE_URL . '/search', $query) ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $candidate = $this->candidate($entry);
            if ($candidate === null) {
                continue;
            }

            $candidates[] = $candidate;
            if (count($candidates) >= self::SEARCH_LIMIT) {
                break;
            }
        }

        return $candidates;
    }

    /** @param array<array-key, mixed> $entry */
    private function candidate(array $entry): ?Candidate
    {
        $isInstrumental = (bool) ($entry['instrumental'] ?? false);
        $hasId = isset($entry['id']) && is_numeric($entry['id']);
        $plain = $this->stringOrNull($entry['plainLyrics'] ?? null);
        $synced = $this->stringOrNull($entry['syncedLyrics'] ?? null);
        if ($isInstrumental || !$hasId || $plain === null && $synced === null) {
            return null;
        }

        return new Candidate(
            source: self::KEY,
            externalId: (string) $entry['id'],
            title: (string) ($this->stringOrNull($entry['trackName'] ?? null) ?? $this->stringOrNull($entry['name'] ?? null) ?? ''),
            artist: $this->stringOrNull($entry['artistName'] ?? null),
            album: $this->stringOrNull($entry['albumName'] ?? null),
            durationSeconds: is_numeric($entry['duration'] ?? null) ? (int) round((float) $entry['duration']) : null,
            plainLyrics: $plain,
            syncedLyrics: $synced,
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
