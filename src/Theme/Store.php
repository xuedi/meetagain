<?php declare(strict_types=1);

namespace App\Theme;

use App\Publisher\PluginSettings\StoreInterface;
use LogicException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class Store implements StoreInterface
{
    /**
     * @param iterable<StoreInterface> $stores
     */
    public function __construct(
        #[AutowireIterator(StoreInterface::class)]
        private iterable $stores,
        #[Autowire(service: 'cache.config')]
        private TagAwareCacheInterface $cache,
    ) {}

    #[Override]
    public function supports(string $key, ?string $scopeId): bool
    {
        return $key === Descriptor::KEY && $this->delegate($key, $scopeId) !== null;
    }

    #[Override]
    public function load(string $key, ?string $scopeId): ?object
    {
        return $this->delegate($key, $scopeId)?->load($key, $scopeId);
    }

    #[Override]
    public function save(string $key, object $data, ?string $scopeId): void
    {
        $store = $this->delegate($key, $scopeId) ?? throw new LogicException('No store persists the theme colours for this scope.');
        $store->save($key, $data, $scopeId);
        $this->cache->invalidateTags([$this->scopeTag($scopeId)]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * @return list<string>
     */
    public function cacheTags(?string $scopeId): array
    {
        return array_values(array_unique([$this->scopeTag(null), $this->scopeTag($scopeId)]));
    }

    private function scopeTag(?string $scopeId): string
    {
        return $scopeId === null ? Descriptor::KEY : Descriptor::KEY . '_scope_' . $scopeId;
    }

    private function delegate(string $key, ?string $scopeId): ?StoreInterface
    {
        $best = null;
        foreach ($this->stores as $store) {
            if (!$store->supports($key, $scopeId)) {
                continue;
            }
            if ($best === null || $store->getPriority() > $best->getPriority()) {
                $best = $store;
            }
        }

        return $best;
    }
}
