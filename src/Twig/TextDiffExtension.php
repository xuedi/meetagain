<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TextDiffExtension extends AbstractExtension
{
    public const int MAX_TOKENS = 400;
    public const float MIN_SIMILARITY = 0.5;
    public const int MAX_RUNS = 6;

    private const string TOKEN_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}]|(?:(?![\p{Han}\p{Hiragana}\p{Katakana}])[\p{L}\p{N}\p{M}])+|\s+|./us';

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('text_diff', $this->textDiff(...)),
        ];
    }

    /**
     * @return array{marked: bool, before: list<array{text: string, changed: bool}>, after: list<array{text: string, changed: bool}>}
     */
    public function textDiff(?string $before, ?string $after): array
    {
        $before = (string) $before;
        $after = (string) $after;
        $plain = [
            'marked' => false,
            'before' => $before === '' ? [] : [['text' => $before, 'changed' => false]],
            'after' => $after === '' ? [] : [['text' => $after, 'changed' => false]],
        ];
        if ($before === '' || $after === '' || $before === $after) {
            return $plain;
        }

        $beforeTokens = $this->tokens($before);
        $afterTokens = $this->tokens($after);
        if (count($beforeTokens) > self::MAX_TOKENS || count($afterTokens) > self::MAX_TOKENS) {
            return $plain;
        }

        $beforeSegments = [];
        $afterSegments = [];
        $runs = 0;
        $removed = [];
        $added = [];
        foreach ($this->joinedOperations($this->operations($beforeTokens, $afterTokens)) as [$op, $token]) {
            if ($op === '-') {
                $removed[] = $token;
                continue;
            }
            if ($op === '+') {
                $added[] = $token;
                continue;
            }
            if ($removed !== [] || $added !== []) {
                $this->appendChange($removed, $added, $beforeSegments, $afterSegments);
                ++$runs;
                $removed = [];
                $added = [];
            }
            $this->append($beforeSegments, $token, false);
            $this->append($afterSegments, $token, false);
        }
        if ($removed !== [] || $added !== []) {
            $this->appendChange($removed, $added, $beforeSegments, $afterSegments);
            ++$runs;
        }

        $unchanged = 0;
        foreach ($beforeSegments as $segment) {
            if (!$segment['changed']) {
                $unchanged += $this->visibleLength($segment['text']);
            }
        }
        $similarity = $unchanged / max(1, $this->visibleLength($before), $this->visibleLength($after));
        if ($unchanged === 0 || $runs > self::MAX_RUNS || $runs > 1 && $similarity < self::MIN_SIMILARITY) {
            return $plain;
        }

        return ['marked' => true, 'before' => $beforeSegments, 'after' => $afterSegments];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches);

        return $matches[0];
    }

    /**
     * @param list<string> $removed
     * @param list<string> $added
     * @param list<array{text: string, changed: bool}> $beforeSegments
     * @param list<array{text: string, changed: bool}> $afterSegments
     */
    private function appendChange(array $removed, array $added, array &$beforeSegments, array &$afterSegments): void
    {
        if (count($removed) === 1 && count($added) === 1 && $this->isWord($removed[0]) && $this->isWord($added[0])) {
            $operations = $this->operations(mb_str_split($removed[0]), mb_str_split($added[0]));
            $same = count(array_filter($operations, static fn(array $operation): bool => $operation[0] === '='));
            if (($same / max(mb_strlen($removed[0]), mb_strlen($added[0]))) >= self::MIN_SIMILARITY) {
                foreach ($operations as [$op, $char]) {
                    if ($op !== '+') {
                        $this->append($beforeSegments, $char, $op === '-');
                    }
                    if ($op !== '-') {
                        $this->append($afterSegments, $char, $op === '+');
                    }
                }

                return;
            }
        }

        foreach ($removed as $token) {
            $this->append($beforeSegments, $token, true);
        }
        foreach ($added as $token) {
            $this->append($afterSegments, $token, true);
        }
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{0: string, 1: string}>
     */
    private function operations(array $a, array $b): array
    {
        $lengthA = count($a);
        $lengthB = count($b);
        $prefix = 0;
        while ($prefix < $lengthA && $prefix < $lengthB && $a[$prefix] === $b[$prefix]) {
            ++$prefix;
        }
        $suffix = 0;
        while ($suffix < ($lengthA - $prefix) && $suffix < ($lengthB - $prefix) && $a[$lengthA - 1 - $suffix] === $b[$lengthB - 1 - $suffix]) {
            ++$suffix;
        }

        $middleA = array_slice($a, $prefix, $lengthA - $prefix - $suffix);
        $middleB = array_slice($b, $prefix, $lengthB - $prefix - $suffix);
        $operations = array_map(static fn(#[\SensitiveParameter] string $token): array => ['=', $token], array_slice($a, 0, $prefix));
        array_push($operations, ...$this->commonSubsequence($middleA, $middleB));
        array_push($operations, ...array_map(static fn(#[\SensitiveParameter] string $token): array => ['=', $token], array_slice($a, $lengthA - $suffix)));

        return $operations;
    }

    /**
     * @param list<array{0: string, 1: string}> $operations
     * @return list<array{0: string, 1: string}>
     */
    private function joinedOperations(array $operations): array
    {
        $joined = [];
        foreach ($operations as $index => [$op, $token]) {
            $previous = $operations[$index - 1][0] ?? '=';
            $next = $operations[$index + 1][0] ?? '=';
            if ($op === '=' && $previous !== '=' && $next !== '=' && trim($token) === '') {
                $joined[] = ['-', $token];
                $joined[] = ['+', $token];
                continue;
            }
            $joined[] = [$op, $token];
        }

        return $joined;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{0: string, 1: string}>
     */
    private function commonSubsequence(array $a, array $b): array
    {
        $lengthA = count($a);
        $lengthB = count($b);
        $table = array_fill(0, $lengthA + 1, array_fill(0, $lengthB + 1, 0));
        for ($i = $lengthA - 1; $i >= 0; --$i) {
            for ($j = $lengthB - 1; $j >= 0; --$j) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;
        while ($i < $lengthA && $j < $lengthB) {
            if ($a[$i] === $b[$j]) {
                $operations[] = ['=', $a[$i]];
                ++$i;
                ++$j;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $operations[] = ['-', $a[$i]];
                ++$i;
            } else {
                $operations[] = ['+', $b[$j]];
                ++$j;
            }
        }
        for (; $i < $lengthA; ++$i) {
            $operations[] = ['-', $a[$i]];
        }
        for (; $j < $lengthB; ++$j) {
            $operations[] = ['+', $b[$j]];
        }

        return $operations;
    }

    /**
     * @param list<array{text: string, changed: bool}> $segments
     */
    private function append(array &$segments, string $text, bool $changed): void
    {
        $last = array_key_last($segments);
        if ($last !== null && $segments[$last]['changed'] === $changed) {
            $segments[$last]['text'] .= $text;

            return;
        }
        $segments[] = ['text' => $text, 'changed' => $changed];
    }

    private function isWord(#[\SensitiveParameter] string $token): bool
    {
        return preg_match('/^[\p{L}\p{N}\p{M}]+$/u', $token) === 1;
    }

    private function visibleLength(string $text): int
    {
        return mb_strlen((string) preg_replace('/\s+/u', '', $text));
    }
}
