<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

use Plugin\Karaoke\Enum\LookupFailure;
use Plugin\Karaoke\Lookup\Candidate;
use Plugin\Karaoke\Lookup\LyricsSourceInterface;
use Plugin\Karaoke\Lookup\Result;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Throwable;

readonly class LyricsLookup
{
    private const int CACHE_SECONDS = 86_400;
    private const int MAX_TITLES = 2;

    /**
     * @param iterable<LyricsSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(LyricsSourceInterface::class)]
        private iterable $sources,
        private ConfigService $configService,
        #[Autowire(service: 'cache.karaoke_lookup')]
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    public function isEnabled(): bool
    {
        return $this->configService->getConfig()->isLookupEnabled() && $this->enabledSources() !== [];
    }

    /**
     * @param list<string> $titles
     */
    public function search(array $titles, ?string $artist, ?int $durationSeconds): Result
    {
        if (!$this->isEnabled()) {
            return new Result([]);
        }

        $found = [];
        $failure = null;
        foreach ($this->enabledSources() as $source) {
            foreach (array_slice($titles, 0, self::MAX_TITLES) as $title) {
                try {
                    foreach ($this->cachedSearch($source, $title, $artist) as $candidate) {
                        $found[$candidate->getKey()] ??= $candidate;
                    }
                } catch (Throwable $exception) {
                    $failure ??= $this->failure($source, $exception);
                }
            }
        }

        return new Result($this->rank(array_values($found), $durationSeconds), $failure);
    }

    public function fetch(string $pick): ?Candidate
    {
        [$sourceKey, $externalId] = array_pad(explode(':', $pick, 2), 2, '');
        $source = array_find($this->enabledSources(), static fn(LyricsSourceInterface $source): bool => $source->getKey() === $sourceKey);
        if ($source === null || $externalId === '' || !$this->configService->getConfig()->isLookupEnabled()) {
            return null;
        }

        try {
            $raw = $this->cache->get($this->cacheKey('fetch', $sourceKey, $externalId), static function (ItemInterface $item) use (
                $source,
                $externalId,
            ): ?array {
                $item->expiresAfter(self::CACHE_SECONDS);

                return $source->fetch($externalId)?->toArray();
            });
        } catch (Throwable $exception) {
            $this->failure($source, $exception);

            return null;
        }

        return is_array($raw) ? Candidate::fromArray($raw) : null;
    }

    /** @return list<Candidate> */
    private function cachedSearch(LyricsSourceInterface $source, string $title, ?string $artist): array
    {
        $raw = $this->cache->get(
            $this->cacheKey('search', $source->getKey(), mb_strtolower($title . "\n" . ($artist ?? ''))),
            static function (ItemInterface $item) use ($source, $title, $artist): array {
                $item->expiresAfter(self::CACHE_SECONDS);

                return array_map(static fn(Candidate $candidate): array => $candidate->toArray(), $source->search($title, $artist));
            },
        );

        return array_map(Candidate::fromArray(...), $raw);
    }

    /**
     * @param list<Candidate> $candidates
     *
     * @return list<Candidate>
     */
    private function rank(array $candidates, ?int $durationSeconds): array
    {
        usort($candidates, static function (Candidate $a, Candidate $b) use ($durationSeconds): int {
            $bySync = $b->isSynced() <=> $a->isSynced();
            if ($bySync !== 0 || $durationSeconds === null) {
                return $bySync;
            }

            $distanceA = $a->durationSeconds === null ? PHP_INT_MAX : abs($a->durationSeconds - $durationSeconds);
            $distanceB = $b->durationSeconds === null ? PHP_INT_MAX : abs($b->durationSeconds - $durationSeconds);

            return $distanceA <=> $distanceB;
        });

        return $candidates;
    }

    private function failure(LyricsSourceInterface $source, Throwable $exception): LookupFailure
    {
        $isRateLimited = $exception instanceof HttpExceptionInterface && $exception->getResponse()->getStatusCode() === 429;
        $this->logger->warning('Karaoke: lyrics lookup failed: ' . $exception->getMessage(), ['source' => $source->getKey()]);

        return $isRateLimited ? LookupFailure::RateLimited : LookupFailure::Unavailable;
    }

    /** @return list<LyricsSourceInterface> */
    private function enabledSources(): array
    {
        $config = $this->configService->getConfig();
        $enabled = array_values(array_filter(iterator_to_array($this->sources, false), static fn(LyricsSourceInterface $source): bool => $source->isEnabled(
            $config,
        )));
        usort($enabled, static fn(LyricsSourceInterface $a, LyricsSourceInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $enabled;
    }

    private function cacheKey(string $kind, string $sourceKey, string $subject): string
    {
        return sprintf('karaoke_%s_%s_%s', $kind, $sourceKey, sha1($subject));
    }
}
