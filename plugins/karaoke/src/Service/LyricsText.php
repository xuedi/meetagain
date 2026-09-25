<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

readonly class LyricsText
{
    private const string TIMESTAMP = '~^\[(?<min>\d{1,3}):(?<sec>[0-5]\d)(?:[.:](?<frac>\d{1,3}))?\]\s*~';
    private const string LRC_TAG = '~^\[[a-z]+:[^\]]*\]$~i';
    private const float MATCH_RATIO = 0.8;

    /** @return list<array{startMs: ?int, text: string, translation: ?string}> */
    public function parse(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = [];
        foreach (preg_split('~\n\s*\n~', trim($normalized)) ?: [] as $block) {
            $rows = array_values(array_filter(
                array_map(trim(...), explode("\n", $block)),
                static fn(string $row): bool => $row !== '' && preg_match(self::LRC_TAG, $row) !== 1,
            ));
            $isTimedList = array_all($rows, static fn(string $row): bool => preg_match(self::TIMESTAMP, $row) === 1);

            if (count($rows) === 1 || $isTimedList) {
                foreach ($rows as $row) {
                    $lines[] = $this->line($row, null);
                }

                continue;
            }

            foreach (array_chunk($rows, 2) as $pair) {
                $lines[] = $this->line($pair[0], $pair[1] ?? null);
            }
        }

        return array_values(array_filter($lines, static fn(array $line): bool => $line['text'] !== ''));
    }

    /** @return list<array{startMs: ?int, text: string, translation: ?string}> */
    public function parseRows(string $text): array
    {
        $lines = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $row) {
            $row = trim($row);
            if ($row === '' || preg_match(self::LRC_TAG, $row) === 1) {
                continue;
            }

            $line = $this->line($row, null);
            if ($line['text'] !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $current
     * @param list<string> $candidate
     */
    public function matchesLines(array $current, array $candidate): bool
    {
        if ($current === [] || count($current) !== count($candidate)) {
            return false;
        }

        $equal = 0;
        foreach ($current as $index => $text) {
            $equal += $this->normalized($text) === $this->normalized($candidate[$index]) ? 1 : 0;
        }

        return ($equal / count($current)) >= self::MATCH_RATIO;
    }

    /** @param list<array{startMs: ?int, text: string, translation: ?string}> $lines */
    public function format(array $lines): string
    {
        $blocks = [];
        foreach ($lines as $line) {
            $block = $line['startMs'] === null ? $line['text'] : '[' . $this->formatTimestamp($line['startMs']) . '] ' . $line['text'];
            if ($line['translation'] !== null && $line['translation'] !== '') {
                $block .= "\n" . $line['translation'];
            }
            $blocks[] = $block;
        }

        return implode("\n\n", $blocks);
    }

    public function parseTimestamp(string $value): ?int
    {
        $trimmed = trim($value, " \t[]");
        if ($trimmed === '') {
            return null;
        }

        $match = [];
        if (preg_match(self::TIMESTAMP, '[' . $trimmed . ']', $match) !== 1 || strlen($match[0]) !== (strlen($trimmed) + 2)) {
            return null;
        }

        return $this->milliseconds($match);
    }

    public function formatTimestamp(int $ms): string
    {
        return sprintf('%02d:%02d.%02d', intdiv($ms, 60_000), intdiv($ms % 60_000, 1000), intdiv($ms % 1000, 10));
    }

    /** @return array{startMs: ?int, text: string, translation: ?string} */
    private function line(string $original, ?string $translation): array
    {
        $startMs = null;
        $match = [];
        if (preg_match(self::TIMESTAMP, $original, $match) === 1) {
            $startMs = $this->milliseconds($match);
            $original = substr($original, strlen($match[0]));
        }

        return [
            'startMs' => $startMs,
            'text' => trim($original),
            'translation' => $translation,
        ];
    }

    /** @param array<array-key, string> $match */
    private function milliseconds(array $match): int
    {
        return ((((int) $match['min'] * 60) + (int) $match['sec']) * 1000) + (int) str_pad($match['frac'] ?? '', 3, '0');
    }

    private function normalized(string $text): string
    {
        return mb_strtolower((string) preg_replace('~[\p{P}\p{S}\s]+~u', '', $text));
    }
}
