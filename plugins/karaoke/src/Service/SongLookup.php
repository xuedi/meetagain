<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

use Plugin\Karaoke\Entity\LyricLine;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Enum\LookupFailure;
use Plugin\Karaoke\Lookup\Candidate;
use Plugin\Karaoke\Lookup\TitleGuesser;
use Plugin\Karaoke\Lookup\VideoReader;
use Plugin\Karaoke\ValueObject\MediaLink;

readonly class SongLookup
{
    private const int PREVIEW_LINES = 2;

    public function __construct(
        private LyricsLookup $lyricsLookup,
        private VideoReader $videoReader,
        private TitleGuesser $titleGuesser,
        private MediaLinkParser $mediaLinkParser,
        private LyricsText $lyricsText,
    ) {}

    public function isEnabled(): bool
    {
        return $this->lyricsLookup->isEnabled();
    }

    public function parseLink(string $input): ?MediaLink
    {
        return $this->mediaLinkParser->parse($input);
    }

    /**
     * @return array{title: string, artist: string, videoTitle: ?string, rows: list<array{candidate: Candidate, preview: list<string>, durationDelta: ?int, timingsOnly: bool}>, failure: ?LookupFailure}
     */
    public function candidatesForLink(MediaLink $link, string $pastedUrl, ?string $title, ?string $artist): array
    {
        $video = $this->videoReader->read($link, $pastedUrl);
        $guess = $this->titleGuesser->guess($video?->title ?? '', $video?->author);
        $titles = $title !== null ? [$title] : $guess->titles;
        $artist = $title !== null ? $artist : $guess->artist;

        return $this->candidates($titles, $artist, $video?->durationSeconds, $video?->title, null);
    }

    /**
     * @return array{title: string, artist: string, videoTitle: ?string, rows: list<array{candidate: Candidate, preview: list<string>, durationDelta: ?int, timingsOnly: bool}>, failure: ?LookupFailure}
     */
    public function candidatesForSong(Song $song, ?string $title, ?string $artist): array
    {
        $video = $this->videoReader->read($song->getMediaLink());
        $titles = [$title ?? (string) $song->getTitle()];
        $artist = $title !== null ? $artist : $song->getArtist();

        return $this->candidates($titles, $artist, $video?->durationSeconds, $video?->title, $song);
    }

    /**
     * @return array{title: ?string, artist: ?string, language: ?string, lyrics: string}
     */
    public function draftForLink(MediaLink $link, string $pastedUrl, string $pick): array
    {
        $candidate = $pick === '' ? null : $this->lyricsLookup->fetch($pick);
        $video = $this->videoReader->read($link, $pastedUrl);
        $guess = $this->titleGuesser->guess($video?->title ?? '', $video?->author);
        $lyrics = $candidate === null ? '' : $this->lyricsText->format($this->lyricsText->parseRows($candidate->getLyrics()));
        $languageSource = $candidate === null ? implode(' ', $guess->titles) : $candidate->title . ' ' . $lyrics;

        return [
            'title' => $candidate?->title ?? $guess->getTitle(),
            'artist' => $candidate?->artist ?? $guess->artist,
            'language' => $this->titleGuesser->language($languageSource) ?? $guess->language,
            'lyrics' => $lyrics,
        ];
    }

    public function lyricsForSong(Song $song, string $pick, string $translationLanguage): ?string
    {
        $candidate = $this->lyricsLookup->fetch($pick);
        if ($candidate === null) {
            return null;
        }

        $existing = $song->getLines()->getValues();
        $lines = [];
        foreach ($this->lyricsText->parseRows($candidate->getLyrics()) as $position => $line) {
            $line['translation'] = ($existing[$position] ?? null)?->findTranslation($translationLanguage)?->getText();
            $lines[] = $line;
        }

        return $this->lyricsText->format($lines);
    }

    /**
     * @return list<?int>|null null when the candidate is untimed or its lines do not match the song's
     */
    public function timingsForSong(Song $song, string $pick): ?array
    {
        $candidate = $this->lyricsLookup->fetch($pick);
        if ($candidate === null || !$candidate->isSynced()) {
            return null;
        }

        $rows = $this->lyricsText->parseRows($candidate->getLyrics());
        if (!$this->lyricsText->matchesLines($this->songTexts($song), array_column($rows, 'text'))) {
            return null;
        }

        return array_column($rows, 'startMs');
    }

    /**
     * @param list<string> $titles
     *
     * @return array{title: string, artist: string, videoTitle: ?string, rows: list<array{candidate: Candidate, preview: list<string>, durationDelta: ?int, timingsOnly: bool}>, failure: ?LookupFailure}
     */
    private function candidates(array $titles, ?string $artist, ?int $durationSeconds, ?string $videoTitle, ?Song $song): array
    {
        $titles = array_values(array_filter($titles, static fn(string $value): bool => trim($value) !== ''));
        $result = $titles === [] ? null : $this->lyricsLookup->search($titles, $artist, $durationSeconds);
        $songTexts = $song === null ? [] : $this->songTexts($song);

        $rows = [];
        foreach ($result->candidates ?? [] as $candidate) {
            $texts = array_column($this->lyricsText->parseRows($candidate->getLyrics()), 'text');
            $hasDurations = $durationSeconds !== null && $candidate->durationSeconds !== null;
            $rows[] = [
                'candidate' => $candidate,
                'preview' => array_slice($texts, 0, self::PREVIEW_LINES),
                'durationDelta' => $hasDurations ? $candidate->durationSeconds - $durationSeconds : null,
                'timingsOnly' => $candidate->isSynced() && $this->lyricsText->matchesLines($songTexts, $texts),
            ];
        }

        return [
            'title' => $titles[0] ?? '',
            'artist' => $artist ?? '',
            'videoTitle' => $videoTitle,
            'rows' => $rows,
            'failure' => $result?->failure,
        ];
    }

    /** @return list<string> */
    private function songTexts(Song $song): array
    {
        return array_map(static fn(LyricLine $line): string => $line->getText(), $song->getLines()->getValues());
    }
}
