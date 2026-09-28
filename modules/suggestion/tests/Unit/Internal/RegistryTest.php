<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Unit\Internal;

use App\Service\Config\PluginService;
use Module\Suggestion\Contract\TargetProviderInterface;
use Module\Suggestion\Internal\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testACoreProviderIsActiveWithoutAppearingInThePluginList(): void
    {
        // Arrange
        $provider = $this->provider('', 'location');
        $registry = $this->makeRegistry([$provider], ['glossary']);

        // Act
        $found = $registry->providerFor('location');

        // Assert
        self::assertSame($provider, $found);
        self::assertTrue($registry->has('location'));
    }

    public function testAProviderOfAnActivePluginIsFoundByTargetType(): void
    {
        // Arrange
        $provider = $this->provider('glossary', 'glossary');
        $registry = $this->makeRegistry([$provider], ['glossary']);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertSame($provider, $found);
    }

    public function testAProviderOfAnInactivePluginIsHidden(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('glossary', 'glossary')], ['dishes']);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertNull($found);
        self::assertFalse($registry->has('glossary'));
    }

    public function testAnUnknownTargetTypeHasNoProvider(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('', 'location')], []);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
    }

    /**
     * @param list<TargetProviderInterface> $providers
     * @param list<string>                            $activePlugins
     */
    private function makeRegistry(array $providers, array $activePlugins): Registry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getGloballyActiveList')->willReturn($activePlugins);

        return new Registry($providers, $pluginService);
    }

    private function provider(string $pluginKey, string $targetType): TargetProviderInterface
    {
        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('getPluginKey')->willReturn($pluginKey);
        $provider->method('getTargetType')->willReturn($targetType);

        return $provider;
    }
}
