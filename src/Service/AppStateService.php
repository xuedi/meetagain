<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\AppStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

class AppStateService implements ResetInterface
{
    private const int MISS_TTL_SECONDS = 60;
    private const int HIT_TTL_SECONDS = 300;

    private bool $cacheFailureLogged = false;

    /** @var array<string, string|null> */
    private array $memo = [];

    public function __construct(
        private readonly AppStateRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.app_state')]
        private readonly CacheInterface&CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(string $key): ?string
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        try {
            $value = $this->cache->get($this->cacheKey($key), function (ItemInterface $item) use ($key): ?string {
                $value = $this->repository->findValue($key);
                $item->expiresAfter($value === null ? self::MISS_TTL_SECONDS : self::HIT_TTL_SECONDS);

                return $value;
            });
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
            $value = $this->repository->findValue($key);
        }

        return $this->memo[$key] = $value;
    }

    /**
     * @param list<string> $keys
     * @return array<string, string|null>
     */
    public function getMany(array $keys): array
    {
        $values = [];
        $pending = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->memo)) {
                $values[$key] = $this->memo[$key];
                continue;
            }
            $pending[$this->cacheKey($key)] = $key;
        }

        if ($pending === []) {
            return $values;
        }

        try {
            $missing = [];
            foreach ($this->cache->getItems(array_keys($pending)) as $cacheKey => $item) {
                $key = $pending[$cacheKey];
                if ($item->isHit()) {
                    $this->memo[$key] = $item->get();
                    $values[$key] = $this->memo[$key];
                    continue;
                }
                $missing[$key] = $item;
            }

            if ($missing !== []) {
                $rows = $this->repository->findValuesByKeys(array_keys($missing));
                foreach ($missing as $key => $item) {
                    $value = $rows[$key] ?? null;
                    $item->expiresAfter($value === null ? self::MISS_TTL_SECONDS : self::HIT_TTL_SECONDS);
                    $item->set($value);
                    $this->cache->saveDeferred($item);
                    $this->memo[$key] = $value;
                    $values[$key] = $value;
                }
                $this->cache->commit();
            }
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
            $rows = $this->repository->findValuesByKeys(array_values($pending));
            foreach ($pending as $key) {
                $this->memo[$key] = $rows[$key] ?? null;
                $values[$key] = $this->memo[$key];
            }
        }

        return $values;
    }

    public function set(string $key, string $value): void
    {
        $this->repository->upsert($key, $value, new DateTimeImmutable('now'));
        $this->invalidate($key);
    }

    public function setIfAbsent(string $key, string $value): string
    {
        $this->repository->insertIfAbsent($key, $value, new DateTimeImmutable('now'));
        $this->invalidate($key);

        return $this->repository->findValue($key) ?? $value;
    }

    public function remove(string $key): void
    {
        $entry = $this->repository->findByKey($key);

        if ($entry === null) {
            return;
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();
        $this->invalidate($key);
    }

    #[Override]
    public function reset(): void
    {
        $this->memo = [];
    }

    private function invalidate(string $key): void
    {
        unset($this->memo[$key]);

        try {
            $this->cache->delete($this->cacheKey($key));
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
        }
    }

    private function cacheKey(string $key): string
    {
        return 'app_state.' . $key;
    }

    private function logCacheFailureOnce(Throwable $exception): void
    {
        if ($this->cacheFailureLogged) {
            return;
        }
        $this->cacheFailureLogged = true;
        $this->logger->warning('AppState cache backend unreachable, falling back to database', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
