<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

final readonly class Candidate
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public ?string $artist = null,
        public ?string $album = null,
        public ?int $durationSeconds = null,
        public ?string $plainLyrics = null,
        public ?string $syncedLyrics = null,
    ) {}

    public function getKey(): string
    {
        return $this->source . ':' . $this->externalId;
    }

    public function isSynced(): bool
    {
        return $this->syncedLyrics !== null && trim($this->syncedLyrics) !== '';
    }

    public function getLyrics(): string
    {
        return $this->isSynced() ? (string) $this->syncedLyrics : (string) $this->plainLyrics;
    }

    /** @return array<string, string|int|null> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'externalId' => $this->externalId,
            'title' => $this->title,
            'artist' => $this->artist,
            'album' => $this->album,
            'durationSeconds' => $this->durationSeconds,
            'plainLyrics' => $this->plainLyrics,
            'syncedLyrics' => $this->syncedLyrics,
        ];
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            source: (string) ($raw['source'] ?? ''),
            externalId: (string) ($raw['externalId'] ?? ''),
            title: (string) ($raw['title'] ?? ''),
            artist: isset($raw['artist']) ? (string) $raw['artist'] : null,
            album: isset($raw['album']) ? (string) $raw['album'] : null,
            durationSeconds: isset($raw['durationSeconds']) ? (int) $raw['durationSeconds'] : null,
            plainLyrics: isset($raw['plainLyrics']) ? (string) $raw['plainLyrics'] : null,
            syncedLyrics: isset($raw['syncedLyrics']) ? (string) $raw['syncedLyrics'] : null,
        );
    }
}
