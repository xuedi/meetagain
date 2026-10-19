<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Publisher\PluginSettings\StoreInterface;
use App\Theme\Config;
use App\Theme\Descriptor;
use App\Theme\Store;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class StoreTest extends TestCase
{
    public function testItClaimsOnlyTheThemeKeyAtAScopeAnotherStorePersists(): void
    {
        // Arrange
        $store = new Store([$this->store(scoped: false)], new TagAwareAdapter(new ArrayAdapter()));

        // Act
        $global = $store->supports(Descriptor::KEY, null);
        $scoped = $store->supports(Descriptor::KEY, '7');
        $otherKey = $store->supports('books', null);

        // Assert
        static::assertTrue($global);
        static::assertFalse($scoped, 'Without a store for the scope the theme record stays global');
        static::assertFalse($otherKey);
    }

    public function testASaveGoesToTheHighestPrioritySupportingStore(): void
    {
        // Arrange
        $low = $this->store(scoped: true, priority: 0);
        $high = $this->store(scoped: true, priority: 5);
        $store = new Store([$low, $high, $this->store(scoped: false)], new TagAwareAdapter(new ArrayAdapter()));

        // Act
        $store->save(Descriptor::KEY, Config::fromArray(['color_link' => '#222222']), '7');

        // Assert
        static::assertNull($low->load(Descriptor::KEY, '7'));
        static::assertInstanceOf(Config::class, $high->load(Descriptor::KEY, '7'));
        static::assertSame($high->load(Descriptor::KEY, '7'), $store->load(Descriptor::KEY, '7'));
    }

    public function testAScopedWriteInvalidatesOnlyThatScope(): void
    {
        // Arrange
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $store = new Store([$this->store(scoped: true)], $cache);
        $this->warm($cache, $store, 'seven', '7');
        $this->warm($cache, $store, 'eight', '8');

        // Act
        $store->save(Descriptor::KEY, Config::fromArray([]), '7');

        // Assert
        static::assertFalse($cache->hasItem('seven'));
        static::assertTrue($cache->hasItem('eight'));
    }

    public function testAGlobalWriteInvalidatesEveryScope(): void
    {
        // Arrange
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $store = new Store([$this->store(scoped: false)], $cache);
        $this->warm($cache, $store, 'global', null);
        $this->warm($cache, $store, 'eight', '8');

        // Act
        $store->save(Descriptor::KEY, Config::fromArray([]), null);

        // Assert
        static::assertFalse($cache->hasItem('global'));
        static::assertFalse($cache->hasItem('eight'));
    }

    private function warm(TagAwareAdapter $cache, Store $store, string $key, ?string $scopeId): void
    {
        $cache->get($key, static function (ItemInterface $item) use ($store, $scopeId): string {
            $item->tag($store->cacheTags($scopeId));

            return 'cached';
        });
    }

    private function store(bool $scoped, int $priority = 0): StoreInterface
    {
        return new class($scoped, $priority) implements StoreInterface {
            /** @var array<string, object> */
            private array $records = [];

            public function __construct(
                private readonly bool $scoped,
                private readonly int $priority,
            ) {}

            public function supports(string $key, ?string $scopeId): bool
            {
                return $this->scoped === ($scopeId !== null);
            }

            public function load(string $key, ?string $scopeId): ?object
            {
                return $this->records[$scopeId ?? 'global'] ?? null;
            }

            public function save(string $key, object $data, ?string $scopeId): void
            {
                $this->records[$scopeId ?? 'global'] = $data;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }
}
