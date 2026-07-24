<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Review\ChangeTargetProviderInterface;
use App\Review\ChangeTargetRegistry;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class ChangeTargetRegistryTest extends TestCase
{
    public function testProviderOfAnActivePluginIsFoundByTargetType(): void
    {
        // Arrange
        $provider = $this->provider('glossary', 'glossary');
        $registry = $this->makeRegistry([$provider], ['glossary']);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertSame($provider, $found);
        self::assertTrue($registry->has('glossary'));
    }

    public function testProviderOfAnInactivePluginIsHidden(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('glossary', 'glossary')], ['dishes']);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertNull($found);
        self::assertFalse($registry->has('glossary'));
    }

    public function testUnknownTargetTypeHasNoProvider(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('glossary', 'glossary')], ['glossary']);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
    }

    /**
     * @param list<ChangeTargetProviderInterface> $providers
     * @param list<string>                        $activePlugins
     */
    private function makeRegistry(array $providers, array $activePlugins): ChangeTargetRegistry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getGloballyActiveList')->willReturn($activePlugins);

        return new ChangeTargetRegistry($providers, $pluginService);
    }

    private function provider(string $pluginKey, string $targetType): ChangeTargetProviderInterface
    {
        $provider = $this->createStub(ChangeTargetProviderInterface::class);
        $provider->method('getPluginKey')->willReturn($pluginKey);
        $provider->method('getTargetType')->willReturn($targetType);

        return $provider;
    }
}
