<?php declare(strict_types=1);

namespace Tests\Unit\Item;

use App\Item\ListCellProviderInterface;
use App\Item\ListCellRegistry;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class ListCellRegistryTest extends TestCase
{
    public function testProviderOfAnActivePluginIsFoundByKey(): void
    {
        // Arrange
        $provider = $this->provider('books', 'book', '<td>a book</td>');
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
        $registry = $this->makeRegistry([$this->provider('books', 'book', '<td>a book</td>')], ['dishes']);

        // Act
        $found = $registry->providerFor('book');

        // Assert
        self::assertNull($found);
        self::assertFalse($registry->has('book'));
    }

    public function testUnknownItemTypeHasNoProvider(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->provider('books', 'book', '<td>a book</td>')], ['books']);

        // Act
        $found = $registry->providerFor('glossary');

        // Assert
        self::assertNull($found);
    }

    public function testEachActiveProviderKeepsItsOwnKey(): void
    {
        // Arrange
        $book = $this->provider('books', 'book', '<td>a book</td>');
        $glossary = $this->provider('glossary', 'glossary', '<td>a phrase</td>');
        $registry = $this->makeRegistry([$book, $glossary], ['books', 'glossary']);

        // Act & Assert
        self::assertSame($book, $registry->providerFor('book'));
        self::assertSame($glossary, $registry->providerFor('glossary'));
    }

    /**
     * @param list<ListCellProviderInterface> $providers
     * @param list<string>                    $activePlugins
     */
    private function makeRegistry(array $providers, array $activePlugins): ListCellRegistry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new ListCellRegistry($providers, $pluginService);
    }

    private function provider(string $pluginKey, string $key, string $cell): ListCellProviderInterface
    {
        $provider = $this->createStub(ListCellProviderInterface::class);
        $provider->method('getPluginKey')->willReturn($pluginKey);
        $provider->method('getKey')->willReturn($key);
        $provider->method('renderListCell')->willReturn($cell);

        return $provider;
    }
}
