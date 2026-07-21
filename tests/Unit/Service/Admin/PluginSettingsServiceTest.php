<?php declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use App\Service\Admin\PluginSettingsService;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Publisher\PluginSettings\Fixtures\StubDescriptor;

class PluginSettingsServiceTest extends TestCase
{
    public function testHasAnyReturnsFalseWithNoProviders(): void
    {
        // Arrange
        $service = new PluginSettingsService([]);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertFalse($result);
    }

    public function testHasAnyReturnsTrueWithProviders(): void
    {
        // Arrange
        $service = new PluginSettingsService([$this->makeProvider('a')]);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertTrue($result);
    }

    public function testGetProvidersReturnsThemInDescendingPriorityOrder(): void
    {
        // Arrange
        $low = $this->makeProvider('low', priority: 0);
        $high = $this->makeProvider('high', priority: 100);
        $mid = $this->makeProvider('mid', priority: 50);
        $service = new PluginSettingsService([$low, $high, $mid]);

        // Act
        $keys = array_keys($service->getProviders());

        // Assert
        static::assertSame(['high', 'mid', 'low'], $keys);
    }

    public function testGetProviderReturnsByKey(): void
    {
        // Arrange
        $provider = $this->makeProvider('filmclub');
        $service = new PluginSettingsService([$provider]);

        // Act + Assert
        static::assertSame($provider, $service->getProvider('filmclub'));
        static::assertNull($service->getProvider('missing'));
    }

    public function testDuplicateKeysThrowOnConstruction(): void
    {
        // Arrange + Act + Assert
        $this->expectException(LogicException::class);
        new PluginSettingsService([
            $this->makeProvider('clash'),
            $this->makeProvider('clash'),
        ]);
    }

    public function testGetScopableByPluginGroupsSectionsUnderTheirPlugin(): void
    {
        // Arrange
        $taxonomy = $this->makeProvider('films_taxonomy', pluginKey: 'films');
        $apiKeys = $this->makeProvider('films', pluginKey: 'films', scopable: false);
        $dishes = $this->makeProvider('dishes');
        $service = new PluginSettingsService([$taxonomy, $apiKeys, $dishes]);

        // Act
        $grouped = $service->getScopableByPlugin();

        // Assert
        static::assertSame([$taxonomy], $grouped['films']);
        static::assertSame([$dishes], $grouped['dishes']);
    }

    public function testGetScopableByPluginOmitsPluginsWithNoScopableSection(): void
    {
        // Arrange
        $service = new PluginSettingsService([$this->makeProvider('films', scopable: false)]);

        // Act
        $grouped = $service->getScopableByPlugin();

        // Assert
        static::assertSame([], $grouped);
    }

    private function makeProvider(
        string $key,
        int $priority = 0,
        ?string $pluginKey = null,
        bool $scopable = true,
    ): PluginSettingsDescriptorInterface {
        return new StubDescriptor($key, $priority, $pluginKey, $scopable);
    }
}
