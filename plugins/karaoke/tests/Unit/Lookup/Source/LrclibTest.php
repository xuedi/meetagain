<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Lookup\Source;

use App\Service\Config\ConfigService;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Lookup\JsonClient;
use Plugin\Karaoke\Lookup\Source\Lrclib;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class LrclibTest extends TestCase
{
    public function testSearchMapsEntriesAndDropsInstrumentals(): void
    {
        // Arrange
        $source = $this->source([new MockResponse((string) json_encode([
            [
                'id' => 101,
                'trackName' => '茉莉花',
                'artistName' => '梁静茹',
                'albumName' => '静茹&情歌',
                'duration' => 209.4,
                'instrumental' => false,
                'plainLyrics' => "好一朵美丽的茉莉花\n芬芳美丽满枝桠",
                'syncedLyrics' => "[00:12.30] 好一朵美丽的茉莉花\n[00:18.00] 芬芳美丽满枝桠",
            ],
            [
                'id' => 102,
                'trackName' => '茉莉花',
                'artistName' => 'Orchestra',
                'duration' => 180,
                'instrumental' => true,
                'plainLyrics' => null,
                'syncedLyrics' => null,
            ],
            [
                'id' => 103,
                'trackName' => '茉莉花',
                'artistName' => '凤飞飞',
                'duration' => 150,
                'instrumental' => false,
                'plainLyrics' => '好一朵茉莉花',
                'syncedLyrics' => null,
            ],
        ]))]);

        // Act
        $candidates = $source->search('茉莉花', null);

        // Assert
        static::assertSame(['101', '103'], array_map(static fn($candidate): string => $candidate->externalId, $candidates));
        static::assertSame('lrclib:101', $candidates[0]->getKey());
        static::assertSame(209, $candidates[0]->durationSeconds);
        static::assertTrue($candidates[0]->isSynced());
        static::assertFalse($candidates[1]->isSynced());
    }

    public function testSearchWithAnArtistFallsBackToAFreeQueryWhenNothingMatches(): void
    {
        // Arrange
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse(
                count($urls) === 1 ? '[]' : (string) json_encode([['id' => 7, 'trackName' => '康定情歌', 'plainLyrics' => '跑马溜溜的山上']]),
            );
        });
        $source = new Lrclib(new JsonClient($client, $this->config()));

        // Act
        $candidates = $source->search('康定情歌', '張惠妹 A-Mei');

        // Assert
        static::assertCount(1, $candidates);
        static::assertStringContainsString('track_name=', $urls[0]);
        static::assertStringContainsString('artist_name=', $urls[0]);
        static::assertStringContainsString('q=', $urls[1]);
    }

    public function testRequestsIdentifyTheInstallInTheUserAgent(): void
    {
        // Arrange
        $headers = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$headers): MockResponse {
            $headers = $options['headers'] ?? [];

            return new MockResponse('[]');
        });
        $source = new Lrclib(new JsonClient($client, $this->config()));

        // Act
        $source->search('茉莉花', null);

        // Assert
        static::assertContains('User-Agent: Dragon Descendants (+https://dragon.example.org) MeetAgain-Karaoke', $headers);
    }

    public function testFetchReturnsNullForAnUnknownId(): void
    {
        // Arrange
        $source = $this->source([new MockResponse('{"message":"Failed to find specified track"}', ['http_code' => 404])]);

        // Act
        $candidate = $source->fetch('999');

        // Assert
        static::assertNull($candidate);
    }

    public function testFetchRejectsAnIdThatIsNotNumeric(): void
    {
        // Arrange
        $source = $this->source([]);

        // Act
        $candidate = $source->fetch('../search');

        // Assert
        static::assertNull($candidate);
    }

    public function testAServerErrorIsThrownForTheLookupToHandle(): void
    {
        // Arrange
        $source = $this->source([new MockResponse('', ['http_code' => 503])]);

        // Assert
        $this->expectException(ExceptionInterface::class);

        // Act
        $source->search('茉莉花', null);
    }

    /** @param list<MockResponse> $responses */
    private function source(array $responses): Lrclib
    {
        return new Lrclib(new JsonClient(new MockHttpClient($responses), $this->config()));
    }

    private function config(): ConfigService
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getSiteName')->willReturn('Dragon Descendants');
        $config->method('getHost')->willReturn('https://dragon.example.org');

        return $config;
    }
}
