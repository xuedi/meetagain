<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

readonly class TitleGuesser
{
    private const string QUOTED = '~[《「『]([^》」』]+)[》」』]~u';
    private const string ARTIST_MARKER = '~(?:演唱|歌手|原唱|主唱)\s*[：:]\s*([^|｜/\[\]【】()（）]+)~u';
    private const string FEATURING = '~\s+(?:feat\.?|ft\.)\s+.*$~iu';
    private const string CHANNEL_SUFFIX = '~\s*[|｜].*$~u';
    private const string BRACKETED = '~\[[^\]]*\]|【[^】]*】~u';
    private const string PARENTHESIZED = '~[(（]([^)）]*)[)）]~u';
    private const string TAG_WORDS = '~(?<![A-Za-z0-9])(?:官方\s*MV|官方版|完整版|高清|动态歌词|動態歌詞|歌词版|歌詞版|Official\s+(?:Music\s+|Lyrics?\s+)?Video|Official\s+Audio|Official\s+MV|Lyrics?\s+Video|Lyrics?|MV|KTV|Karaoke|HD|4K)(?![A-Za-z0-9])~iu';
    private const string TAG_INSIDE = '~official|video|audio|lyric|karaoke|ktv|\bmv\b|\bhd\b|4k|remaster|live|官方|歌词|歌詞|伴奏|高清|字幕~iu';
    private const string ARTIST_SEPARATOR = "~\\s+[-\u{2013}\u{2014}]\\s+~u";
    private const string AUTHOR_NOISE = '~\s*(?:-\s*Topic|VEVO|Official|官方频道|官方頻道)\s*$~iu';

    public function guess(string $videoTitle, ?string $author = null): Guess
    {
        $text = $this->squash($videoTitle);

        $match = [];
        $quoted = preg_match(self::QUOTED, $text, $match) === 1 ? $this->squash($match[1]) : null;
        $artist = preg_match(self::ARTIST_MARKER, $text, $match) === 1 ? $this->squash($match[1]) : null;

        $text = (string) preg_replace([self::ARTIST_MARKER, self::CHANNEL_SUFFIX, self::BRACKETED], ['', '', ' '], $text);

        $alternatives = [];
        $text = (string) preg_replace_callback(
            self::PARENTHESIZED,
            function (array $inner) use (&$alternatives): string {
                $content = $this->squash($inner[1]);
                if ($content !== '' && preg_match(self::TAG_INSIDE, $content) !== 1) {
                    $alternatives[] = $content;

                    return '|';
                }

                return ' ';
            },
            $text,
        );
        $text = $this->squash((string) preg_replace(self::TAG_WORDS, ' ', $text));

        $title = $quoted;
        if ($title === null) {
            $parts = preg_split(self::ARTIST_SEPARATOR, $text, 2) ?: [$text];
            if (count($parts) === 2) {
                $artist ??= $this->squash($parts[0]);
                $title = $this->squash($parts[1]);
            } else {
                $segments = array_map($this->squash(...), explode('|', $text));
                $title = $segments[0];
                $trailing = $segments[1] ?? '';
                $artist ??= $trailing !== '' ? $trailing : null;
            }
        }
        $title = $this->squash((string) preg_replace([self::FEATURING, '~\|~'], ['', ' '], $title));

        $artist ??= $this->authorAsArtist($author);
        $titles = array_values(array_unique(array_filter([$title, ...$alternatives], static fn(string $value): bool => $value !== '')));

        return new Guess(titles: $titles, artist: $artist === '' ? null : $artist, language: $this->language(implode(' ', $titles)));
    }

    public function language(string $text): ?string
    {
        return match (true) {
            preg_match('~[\p{Hiragana}\p{Katakana}]~u', $text) === 1 => 'ja',
            preg_match('~\p{Hangul}~u', $text) === 1 => 'ko',
            preg_match('~\p{Han}~u', $text) === 1 => 'zh',
            default => null,
        };
    }

    private function authorAsArtist(?string $author): ?string
    {
        if ($author === null) {
            return null;
        }

        $cleaned = $this->squash((string) preg_replace(self::AUTHOR_NOISE, '', $author));

        return $cleaned === '' ? null : $cleaned;
    }

    private function squash(string $value): string
    {
        return trim((string) preg_replace('~[\s\x{3000}]+~u', ' ', $value), " \t\n\r\0\x0B-");
    }
}
