<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use App\Publisher\PluginSettings\PluginSettingsScopeProviderInterface;
use App\Publisher\PluginSettings\PluginSettingsStoreInterface;
use App\Service\Admin\PluginSettingsService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Publisher\PluginSettings\Fixtures\StubDescriptor;
use Tests\Unit\Publisher\PluginSettings\Fixtures\StubSettingsData;

class PluginSettingsResolverTest extends TestCase
{
    public function testReturnsOverrideWhenScopeHasStoredRecord(): void
    {
        // Arrange
        $override = new StubSettingsData('override');
        $global = new StubSettingsData('global');
        $resolver = $this->resolver(stores: [
            $this->store(scoped: true, value: $override),
            $this->store(scoped: false, value: $global),
        ], scopeProviders: [$this->scope('7')]);

        // Act + Assert
        static::assertSame($override, $resolver->resolve('stub'));
    }

    public function testFallsBackToGlobalWhenOverrideScopeEmpty(): void
    {
        // Arrange
        $global = new StubSettingsData('global');
        $resolver = $this->resolver(stores: [
            $this->store(scoped: true, value: null),
            $this->store(scoped: false, value: $global),
        ], scopeProviders: [$this->scope('7')]);

        // Act + Assert
        static::assertSame($global, $resolver->resolve('stub'));
    }

    public function testReturnsGlobalWhenNoScopeActive(): void
    {
        // Arrange
        $global = new StubSettingsData('global');
        $resolver = $this->resolver(stores: [$this->store(scoped: false, value: $global)], scopeProviders: []);

        // Act + Assert
        static::assertSame($global, $resolver->resolve('stub'));
    }

    public function testReturnsDescriptorDefaultWhenNothingStored(): void
    {
        // Arrange
        $resolver = $this->resolver(stores: [$this->store(scoped: false, value: null)], scopeProviders: []);

        // Act
        $result = $resolver->resolve('stub');

        // Assert
        static::assertInstanceOf(StubSettingsData::class, $result);
        static::assertSame('default', $result->label);
    }

    public function testUnknownKeyThrows(): void
    {
        // Arrange
        $resolver = $this->resolver(stores: [], scopeProviders: []);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $resolver->resolve('missing');
    }

    public function testResolveStorePicksHighestPrioritySupportingStore(): void
    {
        // Arrange
        $low = $this->store(scoped: false, value: null, priority: -100);
        $high = $this->store(scoped: false, value: null, priority: 10);
        $resolver = $this->resolver(stores: [$low, $high], scopeProviders: []);

        // Act
        $selected = $resolver->resolveStore('stub', null);

        // Assert
        static::assertSame($high, $selected);
    }

    public function testResolveStoreIgnoresStoresForDisjointScope(): void
    {
        // Arrange
        $globalOnly = $this->store(scoped: false, value: null);
        $overrideOnly = $this->store(scoped: true, value: null);
        $resolver = $this->resolver(stores: [$globalOnly, $overrideOnly], scopeProviders: []);

        // Act + Assert
        static::assertSame($globalOnly, $resolver->resolveStore('stub', null));
        static::assertSame($overrideOnly, $resolver->resolveStore('stub', '7'));
    }

    /**
     * @param list<PluginSettingsStoreInterface>         $stores
     * @param list<PluginSettingsScopeProviderInterface> $scopeProviders
     */
    private function resolver(array $stores, array $scopeProviders): PluginSettingsResolver
    {
        return new PluginSettingsResolver(new PluginSettingsService([new StubDescriptor('stub')]), $stores, $scopeProviders);
    }

    private function store(bool $scoped, ?object $value, int $priority = 0): PluginSettingsStoreInterface
    {
        return new class($scoped, $value, $priority) implements PluginSettingsStoreInterface {
            public function __construct(
                private readonly bool $scoped,
                private readonly ?object $value,
                private readonly int $priority,
            ) {}

            public function supports(string $key, ?string $scopeId): bool
            {
                return $this->scoped ? $scopeId !== null : $scopeId === null;
            }

            public function load(string $key, ?string $scopeId): ?object
            {
                return $this->value;
            }

            public function save(string $key, object $data, ?string $scopeId): void {}

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }

    private function scope(?string $id): PluginSettingsScopeProviderInterface
    {
        return new class($id) implements PluginSettingsScopeProviderInterface {
            public function __construct(
                private readonly ?string $id,
            ) {}

            public function getScopeId(): ?string
            {
                return $this->id;
            }
        };
    }
}
