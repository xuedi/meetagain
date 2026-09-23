<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Service\LyricsText;

class LyricsTextTest extends TestCase
{
    public function testParsesOriginalAndTranslationPairsSeparatedByBlankLines(): void
    {
        // Arrange
        $text = "少林，少林\r\nShaolin, Shaolin\r\n\r\n\r\n有多少英雄豪杰都来把你敬仰\nSo many heroes\n";

        // Act
        $lines = new LyricsText()->parse($text);

        // Assert
        static::assertSame(
            [
                ['startMs' => null, 'text' => '少林，少林', 'translation' => 'Shaolin, Shaolin'],
                ['startMs' => null, 'text' => '有多少英雄豪杰都来把你敬仰', 'translation' => 'So many heroes'],
            ],
            $lines,
        );
    }

    public function testReadsATimestampPrefixIntoMilliseconds(): void
    {
        // Act
        $lines = new LyricsText()->parse("[00:34.18] Oublie-le\nForget it\n\n[1:02.5]Je t'aimais\n\n[10:00] 刻在我心底的名字");

        // Assert
        static::assertSame(34_180, $lines[0]['startMs']);
        static::assertSame('Oublie-le', $lines[0]['text']);
        static::assertSame('Forget it', $lines[0]['translation']);
        static::assertSame(62_500, $lines[1]['startMs']);
        static::assertNull($lines[1]['translation']);
        static::assertSame(600_000, $lines[2]['startMs']);
    }

    public function testAnLrcFileBecomesOneUntranslatedLinePerTimestamp(): void
    {
        // Arrange
        $lrc = "[ar:盧廣仲]\n[ti:刻在我心底的名字]\n[00:34.18] Oublie-le\n[00:38.35] 好几次我告诉我自己\n[00:44.00] \n[00:45.51] 越想努力赶上光的影";

        // Act
        $lines = new LyricsText()->parse($lrc);

        // Assert
        static::assertSame([34_180, 38_350, 45_510], array_column($lines, 'startMs'));
        static::assertSame([null, null, null], array_column($lines, 'translation'));
    }

    public function testFormatIsTheInverseOfParse(): void
    {
        // Arrange
        $text = "[00:34.18] Oublie-le\nForget it\n\n好几次我告诉我自己\nSo many times I've told myself\n\n[01:02.81] Je t'aimais";
        $lyrics = new LyricsText();

        // Act
        $formatted = $lyrics->format($lyrics->parse($text));

        // Assert
        static::assertSame($text, $formatted);
    }

    #[DataProvider('timestamps')]
    public function testParseTimestampAcceptsTheFormatsItWrites(string $value, ?int $expected): void
    {
        // Act
        $ms = new LyricsText()->parseTimestamp($value);

        // Assert
        static::assertSame($expected, $ms);
    }

    public static function timestamps(): iterable
    {
        yield 'centiseconds' => ['01:02.34', 62_340];
        yield 'milliseconds' => ['01:02.345', 62_345];
        yield 'no fraction' => ['1:02', 62_000];
        yield 'bracketed' => ['[00:34.18]', 34_180];
        yield 'surrounding space' => ['  00:05.00 ', 5000];
        yield 'empty is untimed' => ['', null];
        yield 'seconds out of range' => ['00:75.00', null];
        yield 'trailing text' => ['00:05.00 hello', null];
        yield 'garbage' => ['soon', null];
    }

    public function testFormatTimestampRoundTripsAtCentisecondPrecision(): void
    {
        // Arrange
        $lyrics = new LyricsText();

        // Act
        $formatted = $lyrics->formatTimestamp(62_345);

        // Assert
        static::assertSame('01:02.34', $formatted);
        static::assertSame(62_340, $lyrics->parseTimestamp($formatted));
    }
}
