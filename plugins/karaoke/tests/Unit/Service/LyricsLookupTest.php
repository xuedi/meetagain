<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Service;

use Closure;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Enum\LookupFailure;
use Plugin\Karaoke\Lookup\Candidate;
use Plugin\Karaoke\Lookup\LyricsSourceInterface;
use Plugin\Karaoke\Service\ConfigService;
use Plugin\Karaoke\Service\LyricsLookup;
use Plugin\Karaoke\ValueObject\Config;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LyricsLookupTest extends TestCase
{
    public function testTimedLyricsComeFirstThenTheClosestDuration(): void
    {
        // Arrange
        $lookup = $this->lookup($this->source([
            $this->candidate('1', null, 200),
            $this->candidate('2', '[00:01.00] a', 260),
            $this->candidate('3', '[00:01.00] a', 205),
            $this->candidate('4', null, 201),
        ]));

        // Act
        $result = $lookup->search(['茉莉花'], null, 200);

        // Assert
        static::assertSame(['3', '2', '1', '4'], array_map(static fn(Candidate $candidate): string => $candidate->externalId, $result->candidates));
        static::assertNull($result->failure);
    }

    public function testWithoutAVideoDurationTheSourceOrderStandsWithinEachGroup(): void
    {
        // Arrange
        $lookup = $this->lookup($this->source([
            $this->candidate('1', null, 200),
            $this->candidate('2', '[00:01.00] a', 260),
            $this->candidate('3', '[00:01.00] a', 205),
        ]));

        // Act
        $result = $lookup->search(['茉莉花'], null, null);

        // Assert
        static::assertSame(['2', '3', '1'], array_map(static fn(Candidate $candidate): string => $candidate->externalId, $result->candidates));
    }

    public function testTheSameHitFoundForTwoTitlesIsListedOnce(): void
    {
        // Arrange
        $lookup = $this->lookup($this->source([$this->candidate('1', null, 200)]));

        // Act
        $result = $lookup->search(['Your Name Engraved Herein', '刻在我心底的名字'], null, null);

        // Assert
        static::assertCount(1, $result->candidates);
    }

    public function testARateLimitedSourceIsReportedAndDoesNotThrow(): void
    {
        // Arrange
        $response = new MockHttpClient([new MockResponse('', ['http_code' => 429])])->request('GET', 'https://lrclib.net/api/search');
        $source = $this->createStub(LyricsSourceInterface::class);
        $source->method('isEnabled')->willReturn(true);
        $source->method('getKey')->willReturn('lrclib');
        $source->method('search')->willThrowException(new ClientException($response));
        $lookup = $this->lookup($source);

        // Act
        $result = $lookup->search(['茉莉花'], null, null);

        // Assert
        static::assertSame([], $result->candidates);
        static::assertSame(LookupFailure::RateLimited, $result->failure);
    }

    public function testAnyOtherErrorIsReportedAsUnavailable(): void
    {
        // Arrange
        $source = $this->createStub(LyricsSourceInterface::class);
        $source->method('isEnabled')->willReturn(true);
        $source->method('getKey')->willReturn('lrclib');
        $source->method('search')->willThrowException(new RuntimeException('timeout'));
        $source->method('fetch')->willThrowException(new RuntimeException('timeout'));
        $lookup = $this->lookup($source);

        // Act
        $result = $lookup->search(['茉莉花'], null, null);
        $fetched = $lookup->fetch('lrclib:1');

        // Assert
        static::assertSame(LookupFailure::Unavailable, $result->failure);
        static::assertNull($fetched);
    }

    public function testASwitchedOffLookupAsksNoSource(): void
    {
        // Arrange
        $source = $this->createMock(LyricsSourceInterface::class);
        $source->method('isEnabled')->willReturn(true);
        $source->expects($this->never())->method('search');
        $lookup = $this->lookup($source, new Config()->setLookupEnabled(false));

        // Act
        $result = $lookup->search(['茉莉花'], null, null);

        // Assert
        static::assertFalse($lookup->isEnabled());
        static::assertSame([], $result->candidates);
    }

    public function testASearchIsAnsweredFromTheCacheTheSecondTime(): void
    {
        // Arrange
        $source = $this->createMock(LyricsSourceInterface::class);
        $source->method('isEnabled')->willReturn(true);
        $source->method('getKey')->willReturn('lrclib');
        $source
            ->expects($this->once())
            ->method('search')
            ->willReturn([$this->candidate('1', null, 200)]);
        $lookup = $this->lookup($source);

        // Act
        $lookup->search(['茉莉花'], null, null);
        $second = $lookup->search(['茉莉花'], null, null);

        // Assert
        static::assertSame('茉莉花 1', $second->candidates[0]->title);
    }

    public function testFetchResolvesAPickOfSourceAndId(): void
    {
        // Arrange
        $lookup = $this->lookup($this->source([], fn(string $id): ?Candidate => $id === '42' ? $this->candidate('42', '[00:01.00] a', 200) : null));

        // Act
        $known = $lookup->fetch('lrclib:42');
        $otherSource = $lookup->fetch('musixmatch:42');

        // Assert
        static::assertSame('42', $known?->externalId);
        static::assertNull($otherSource);
    }

    /** @param list<Candidate> $candidates */
    private function source(array $candidates, ?Closure $fetch = null): LyricsSourceInterface
    {
        $source = $this->createStub(LyricsSourceInterface::class);
        $source->method('isEnabled')->willReturn(true);
        $source->method('getKey')->willReturn('lrclib');
        $source->method('search')->willReturn($candidates);
        $source->method('fetch')->willReturnCallback($fetch ?? static fn(): ?Candidate => null);

        return $source;
    }

    private function candidate(string $id, ?string $synced, ?int $duration): Candidate
    {
        return new Candidate(source: 'lrclib', externalId: $id, title: '茉莉花 ' . $id, durationSeconds: $duration, plainLyrics: 'a', syncedLyrics: $synced);
    }

    private function lookup(LyricsSourceInterface $source, ?Config $config = null): LyricsLookup
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config ?? new Config());

        return new LyricsLookup([$source], $configService, new ArrayAdapter(), new NullLogger());
    }
}
