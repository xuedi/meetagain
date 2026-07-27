<?php declare(strict_types=1);

namespace Tests\Unit\Item;

use App\Item\ListProviderInterface;
use App\Item\ListRegistry;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class ListRegistryTest extends TestCase
{
    public function testProviderOfAnActivePluginIsFoundByKey(): void
    {
        // Arrange
        $provider = $this->provider('books', 'book');
        $registry = $this->makeRegistry([$provider], ['books']);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertSame($provider, $found);
        self::assertTrue($registry->has('book'));
    }

    public function testProviderOfAnInactivePluginIsHidden(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('books', 'book')], ['dishes']);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
        self::assertFalse($registry->has('book'));
    }

    public function testUnknownItemTypeHasNoProvider(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('books', 'book')], ['books']);

        // Act + Assert
        self::assertNull($registry->providerFor('glossary'));
    }

    public function testEachActiveProviderKeepsItsOwnKey(): void
    {
        // Arrange
        $book = $this->provider('books', 'book');
        $glossary = $this->provider('glossary', 'glossary');
        $registry = $this->makeRegistry([$book, $glossary], ['books', 'glossary']);

        // Act & Assert
        self::assertSame($book, $registry->providerFor('book'));
        self::assertSame($glossary, $registry->providerFor('glossary'));
    }

    /**
     * @param list<ListProviderInterface> $providers
     * @param list<string>                $activePlugins
     */
    private function makeRegistry(array $providers, array $activePlugins): ListRegistry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new ListRegistry($providers, $pluginService);
    }

    private function provider(string $pluginKey, string $key): ListProviderInterface
    {
        $provider = $this->createStub(ListProviderInterface::class);
        $provider->method('getPluginKey')->willReturn($pluginKey);
        $provider->method('getKey')->willReturn($key);

        return $provider;
    }
}
