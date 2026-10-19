<?php declare(strict_types=1);

namespace App\Theme;

use App\Publisher\PluginSettings\GenericStore;
use App\Publisher\PluginSettings\StoreInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class Store implements StoreInterface
{
    public function __construct(
        private GenericStore $records,
        #[Autowire(service: 'cache.config')]
        private CacheInterface $cache,
    ) {}

    #[Override]
    public function supports(string $key, ?string $scopeId): bool
    {
        return $key === Descriptor::KEY && $scopeId === null;
    }

    #[Override]
    public function load(string $key, ?string $scopeId): ?object
    {
        return $this->records->load($key, $scopeId);
    }

    #[Override]
    public function save(string $key, object $data, ?string $scopeId): void
    {
        $this->records->save($key, $data, $scopeId);
        $this->cache->delete(Colors::COLORS_CACHE_KEY);
        $this->cache->delete(Colors::STYLE_CACHE_KEY);
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }
}
