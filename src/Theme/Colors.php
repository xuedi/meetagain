<?php declare(strict_types=1);

namespace App\Theme;

use App\Publisher\PluginSettings\Resolver;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class Colors
{
    public const string COLORS_CACHE_KEY = 'theme_colors';
    public const string STYLE_CACHE_KEY = 'theme_inline_style';

    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private Resolver $resolver,
        private Descriptor $descriptor,
        private CssRenderer $renderer,
        #[Autowire(service: 'cache.config')]
        private CacheInterface $cache,
    ) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->cache->get(self::COLORS_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->current()->toArray();
        });
    }

    /**
     * @param array<array-key, mixed> $colors
     */
    public function save(array $colors): void
    {
        $store = $this->resolver->resolveStore(Descriptor::KEY, null) ?? throw new LogicException('No store holds the theme colours.');
        $store->save(Descriptor::KEY, $this->current()->with($colors), null);
    }

    public function hasStoredTheme(): bool
    {
        return $this->resolver->resolveStore(Descriptor::KEY, null)?->load(Descriptor::KEY, null) !== null;
    }

    public function inlineStyle(): string
    {
        return $this->cache->get(self::STYLE_CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->renderer->render($this->current(), $this->descriptor->compiledBaseline());
        });
    }

    private function current(): Config
    {
        $stored = $this->resolver->resolveStore(Descriptor::KEY, null)?->load(Descriptor::KEY, null);
        $baseline = $this->descriptor->compiledBaseline();

        return $stored instanceof Config ? $baseline->with($stored->toArray()) : $baseline;
    }
}
