<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\Service\MediaLinkParser;

class MediaLinkParserTest extends TestCase
{
    #[DataProvider('acceptedLinks')]
    public function testParsesALinkIntoProviderAndBareId(string $input, MediaProvider $provider, string $id): void
    {
        // Act
        $link = new MediaLinkParser()->parse($input);

        // Assert
        static::assertNotNull($link);
        static::assertSame($provider, $link->provider);
        static::assertSame($id, $link->id);
        static::assertTrue($provider->isValidId($link->id));
    }

    public static function acceptedLinks(): iterable
    {
        $yt = MediaProvider::YouTube;
        yield 'youtube watch with playlist noise' => ['https://www.youtube.com/watch?v=oeRz7Cu97YU&list=RDoeRz7Cu97YU&start_radio=1', $yt, 'oeRz7Cu97YU'];
        yield 'youtube watch with v not first' => ['https://www.youtube.com/watch?feature=share&v=1czgDI1Vy88', $yt, '1czgDI1Vy88'];
        yield 'youtube without scheme' => ['youtube.com/watch?v=1czgDI1Vy88', $yt, '1czgDI1Vy88'];
        yield 'youtube mobile host' => ['https://m.youtube.com/watch?v=1czgDI1Vy88', $yt, '1czgDI1Vy88'];
        yield 'youtube music host' => ['https://music.youtube.com/watch?v=1czgDI1Vy88&si=abc', $yt, '1czgDI1Vy88'];
        yield 'youtube short host with time' => ['https://youtu.be/1czgDI1Vy88?t=42', $yt, '1czgDI1Vy88'];
        yield 'youtube shorts' => ['https://www.youtube.com/shorts/1czgDI1Vy88', $yt, '1czgDI1Vy88'];
        yield 'youtube embed' => ['https://www.youtube.com/embed/1czgDI1Vy88', $yt, '1czgDI1Vy88'];
        yield 'youtube nocookie embed' => ['https://www.youtube-nocookie.com/embed/1czgDI1Vy88?rel=0', $yt, '1czgDI1Vy88'];
        yield 'youtube with surrounding whitespace' => ['  https://youtu.be/oeRz7Cu97YU  ', $yt, 'oeRz7Cu97YU'];
        yield 'tiktok video page' => ['https://www.tiktok.com/@some.user/video/7304522915423243562?lang=en', MediaProvider::TikTok, '7304522915423243562'];
        yield 'tiktok player' => ['https://www.tiktok.com/player/v1/7304522915423243562', MediaProvider::TikTok, '7304522915423243562'];
        yield 'tiktok embed' => ['https://www.tiktok.com/embed/v2/7304522915423243562', MediaProvider::TikTok, '7304522915423243562'];
        yield 'bilibili video page' => ['https://www.bilibili.com/video/BV1GJ411x7h7/?spm_id_from=333', MediaProvider::Bilibili, 'BV1GJ411x7h7'];
        yield 'bilibili player' => ['https://player.bilibili.com/player.html?aid=1&bvid=BV1GJ411x7h7&cid=2', MediaProvider::Bilibili, 'BV1GJ411x7h7'];
    }

    #[DataProvider('rejectedLinks')]
    public function testRejectsUnsupportedInput(string $input): void
    {
        // Act
        $link = new MediaLinkParser()->parse($input);

        // Assert
        static::assertNull($link);
    }

    public static function rejectedLinks(): iterable
    {
        yield 'empty' => [''];
        yield 'bare id' => ['oeRz7Cu97YU'];
        yield 'youtube channel' => ['https://www.youtube.com/@someone'];
        yield 'youtube id too short' => ['https://youtu.be/oeRz7Cu97Y'];
        yield 'youtube id too long' => ['https://youtu.be/oeRz7Cu97YUx'];
        yield 'lookalike host' => ['https://youtube.com.evil.example/watch?v=oeRz7Cu97YU'];
        yield 'tiktok short link' => ['https://vm.tiktok.com/ZMabcdef/'];
        yield 'bilibili short link' => ['https://b23.tv/abcdef'];
        yield 'other host' => ['https://vimeo.com/123456789'];
    }
}
