<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\RootProviderInterface;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

final class ScoreProvider implements ResetInterface
{
    private bool $cacheFailureLogged = false;

    /** @var array<string, array<int, int>> */
    private array $memo = [];

    /**
     * @param iterable<ActionSourceInterface>  $actionSources
     * @param iterable<RootProviderInterface>  $rootProviders
     */
    public function __construct(
        #[AutowireIterator(ActionSourceInterface::class)]
        private readonly iterable $actionSources,
        #[AutowireIterator(RootProviderInterface::class)]
        private readonly iterable $rootProviders,
        private readonly ContextRegistry $registry,
        private readonly ActionRegistry $actionRegistry,
        private readonly ConfigStore $configStore,
        private readonly ScoreCalculator $calculator,
        private readonly TrustGrantRepository $grants,
        #[Autowire(service: 'cache.trust')]
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<int, int>
     */
    public function getMap(string $context): array
    {
        if (array_key_exists($context, $this->memo)) {
            return $this->memo[$context];
        }

        if (!$this->registry->exists($context)) {
            return $this->memo[$context] = [];
        }

        $revision = $this->buildRevision($context);

        try {
            $item = $this->cache->getItem($this->cacheKey($context));
            $cached = $item->isHit() ? $item->get() : null;

            if (!is_array($cached) || ($cached['revision'] ?? null) !== $revision) {
                $cached = ['revision' => $revision, 'map' => $this->calculate($context)];
                $item->expiresAfter(null);
                $item->set($cached);
                $this->cache->save($item);
            }
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
            $cached = ['revision' => $revision, 'map' => $this->calculate($context)];
        }

        return $this->memo[$context] = $cached['map'];
    }

    public function invalidate(string $context): void
    {
        unset($this->memo[$context]);

        try {
            $this->cache->deleteItem($this->cacheKey($context));
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
        }
    }

    /**
     * @return array<int, int>
     */
    public function calculate(string $context): array
    {
        $config = $this->configStore->get($context);

        return $this->calculator->compute($this->buildBasePoints($context, $config), $this->grants->findEdges($context), $config);
    }

    #[Override]
    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * @return list<string>
     */
    public function findUndeclaredActions(string $context): array
    {
        $declared = $this->actionRegistry->forContext($context);
        $undeclared = [];
        foreach ($this->actionSources as $source) {
            foreach ($source->replay($context) as $action) {
                if (isset($declared[$action->action])) {
                    continue;
                }
                $undeclared[$action->action] = true;
            }
        }

        return array_keys($undeclared);
    }

    /**
     * @return array<int, int>
     */
    private function buildBasePoints(string $context, TrustConfig $config): array
    {
        $points = [];

        foreach ($this->rootProviders as $provider) {
            foreach ($provider->getRootUserIds($context) as $userId) {
                if (isset($points[$userId])) {
                    continue;
                }
                $root = $this->resolveRootPoints($context, $userId);
                if ($root !== null) {
                    $points[$userId] = $root;
                }
            }
        }

        $descriptors = $this->actionRegistry->forContext($context);
        foreach ($this->collectQuantities($context, $descriptors) as $userId => $perAction) {
            foreach ($perAction as $key => $quantity) {
                $descriptor = $descriptors[$key];
                $cap = $config->capFor($descriptor);
                $counted = $cap === null ? $quantity : min($cap, $quantity);
                $points[$userId] = ($points[$userId] ?? 0) + $config->pointsFor($descriptor) * $counted;
            }
        }

        return $points;
    }

    /**
     * @param array<string, ActionDescriptor> $descriptors
     * @return array<int, array<string, int>>
     */
    private function collectQuantities(string $context, array $descriptors): array
    {
        $quantities = [];
        foreach ($this->actionSources as $source) {
            foreach ($source->replay($context) as $action) {
                if (!isset($descriptors[$action->action])) {
                    continue;
                }
                $quantities[$action->userId][$action->action] =
                    ($quantities[$action->userId][$action->action] ?? 0) + max(0, $action->quantity);
            }
        }

        return $quantities;
    }

    private function resolveRootPoints(string $context, int $userId): ?int
    {
        foreach ($this->rootProviders as $provider) {
            $root = $provider->getRootPoints($context, $userId);
            if ($root !== null) {
                return $root;
            }
        }

        return null;
    }

    private function buildRevision(string $context): string
    {
        $parts = [];
        foreach ($this->actionSources as $source) {
            $parts[] = $source->getRevision($context) ?? '-';
        }
        sort($parts);
        $parts[] = $this->grants->findRevision($context) ?? '-';
        $parts[] = $this->configStore->getRevision($context) ?? '-';

        return implode('|', $parts);
    }

    private function cacheKey(string $context): string
    {
        return 'trust.map.' . hash('xxh128', $context);
    }

    private function logCacheFailureOnce(Throwable $exception): void
    {
        if ($this->cacheFailureLogged) {
            return;
        }
        $this->cacheFailureLogged = true;
        $this->logger->warning('Trust cache backend unreachable, computing scores directly', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
