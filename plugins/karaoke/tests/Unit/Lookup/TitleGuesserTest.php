<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Lookup;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Lookup\TitleGuesser;

class TitleGuesserTest extends TestCase
{
    /**
     * @param list<string> $titles
     */
    #[DataProvider('realTitles')]
    public function testGuessesTitleArtistAndLanguageFromARealVideoTitle(
        string $videoTitle,
        ?string $author,
        array $titles,
        ?string $artist,
        ?string $language,
    ): void {
        // Act
        $guess = new TitleGuesser()->guess($videoTitle, $author);

        // Assert
        static::assertSame($titles, $guess->titles);
        static::assertSame($artist, $guess->artist);
        static::assertSame($language, $guess->language);
    }

    /** @return iterable<string, array{string, ?string, list<string>, ?string, ?string}> */
    public static function realTitles(): iterable
    {
        yield 'book-title brackets win and the singer marker names the artist' => [
            '[民歌中国]江苏民歌《茉莉花》 演唱：张也|中国舞台',
            'CCTV中国中央电视台',
            ['茉莉花'],
            '张也',
            'zh',
        ];
        yield 'artist before the dash, MV tags dropped' => [
            '張惠妹 A-Mei - 康定情歌 官方MV (Official Music Video)',
            '華納音樂 Warner Music Taiwan',
            ['康定情歌'],
            '張惠妹 A-Mei',
            'zh',
        ];
        yield 'a parenthesized translation is tried as a second title' => [
            '[KARAOKE] Your Name Engraved Herein (刻在我心底的名字) Crowd Lu 盧廣仲 [CHI-ROM-ENG]',
            null,
            ['Your Name Engraved Herein', '刻在我心底的名字'],
            'Crowd Lu 盧廣仲',
            'zh',
        ];
        yield 'a bare title falls back to the channel as the artist' => [
            'Shaolin Temple Theme Song',
            'Shaolin Records - Topic',
            ['Shaolin Temple Theme Song'],
            'Shaolin Records',
            null,
        ];
        yield 'featured artists are cut from the title' => [
            'Artist Name - Song Title feat. Somebody Else [Lyrics]',
            null,
            ['Song Title'],
            'Artist Name',
            null,
        ];
    }

    #[DataProvider('scripts')]
    public function testGuessesTheLanguageFromTheScript(string $text, ?string $language): void
    {
        // Act
        $guessed = new TitleGuesser()->language($text);

        // Assert
        static::assertSame($language, $guessed);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function scripts(): iterable
    {
        yield 'kana means Japanese even next to Han' => ['君の名は', 'ja'];
        yield 'hangul means Korean' => ['사랑해요', 'ko'];
        yield 'Han alone means Chinese' => ['茉莉花', 'zh'];
        yield 'Latin script is left open' => ['Jasmine Flower', null];
    }
}
