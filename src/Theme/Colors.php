<?php declare(strict_types=1);

namespace App\Theme;

use App\Publisher\PluginSettings\Resolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class Colors
{
    private const string GLOBAL_SCOPE = 'global';
    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private Resolver $resolver,
        private Store $store,
        private Descriptor $descriptor,
        private CssRenderer $renderer,
        #[Autowire(service: 'cache.config')]
        private TagAwareCacheInterface $cache,
    ) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->forScope($this->resolver->resolveScopeId());
    }

    /**
     * @return array<string, string>
     */
    public function forScope(?string $scopeId): array
    {
        return $this->cache->get($this->cacheKey('theme_colors', $scopeId), function (ItemInterface $item) use ($scopeId): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            $item->tag($this->store->cacheTags($scopeId));

            return $this->effective($scopeId)->toArray();
        });
    }

    /**
     * @param array<array-key, mixed> $colors
     */
    public function save(array $colors, ?string $scopeId): void
    {
        $merged = $this->effective($scopeId)->with($colors);
        if ($scopeId !== null) {
            $merged = Config::fromArray(array_diff_assoc($merged->toArray(), $this->effective(null)->toArray()));
        }

        $this->store->save(Descriptor::KEY, $merged, $scopeId);
    }

    public function reset(?string $scopeId): void
    {
        $this->store->save(Descriptor::KEY, Config::fromArray([]), $scopeId);
    }

    public function hasStoredTheme(?string $scopeId): bool
    {
        return $this->load($scopeId) !== null;
    }

    public function inlineStyle(): string
    {
        $scopeId = $this->resolver->resolveScopeId();

        return $this->cache->get($this->cacheKey('theme_inline_style', $scopeId), function (ItemInterface $item) use ($scopeId): string {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            $item->tag($this->store->cacheTags($scopeId));

            return $this->renderer->render($this->effective($scopeId), $this->descriptor->compiledBaseline());
        });
    }

    private function effective(?string $scopeId): Config
    {
        $colors = $this->descriptor->compiledBaseline()->with($this->load(null)?->toArray() ?? []);

        return $scopeId === null ? $colors : $colors->with($this->load($scopeId)?->toArray() ?? []);
    }

    private function load(?string $scopeId): ?Config
    {
        $stored = $this->store->load(Descriptor::KEY, $scopeId);

        return $stored instanceof Config ? $stored : null;
    }

    private function cacheKey(string $name, ?string $scopeId): string
    {
        return $name . '_' . ($scopeId ?? self::GLOBAL_SCOPE);
    }
}
