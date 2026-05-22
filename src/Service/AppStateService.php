<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AppState;
use App\Repository\AppStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class AppStateService
{
    private bool $cacheFailureLogged = false;

    public function __construct(
        private readonly AppStateRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.app_state')]
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(string $key): ?string
    {
        try {
            return $this->cache->get($this->cacheKey($key), function (ItemInterface $item) use ($key): ?string {
                $item->expiresAfter(null);

                return $this->repository->findByKey($key)?->getValue();
            });
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);

            return $this->repository->findByKey($key)?->getValue();
        }
    }

    public function set(string $key, string $value): void
    {
        $entry = $this->repository->findByKey($key);

        if ($entry === null) {
            $entry = new AppState($key, $value, new DateTimeImmutable('now'));
            $this->entityManager->persist($entry);
            $this->entityManager->flush();
            $this->invalidate($key);
            return;
        }
        $entry->setValue($value);
        $entry->setUpdatedAt(new DateTimeImmutable('now'));

        $this->entityManager->flush();
        $this->invalidate($key);
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

    private function invalidate(string $key): void
    {
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
