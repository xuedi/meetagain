<?php declare(strict_types=1);

namespace Tests\Unit\Item\Portability;

use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\ItemPortabilityRegistry;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class ItemPortabilityRegistryTest extends TestCase
{
    public function testContributorOfAnActivePluginIsFoundByItemType(): void
    {
        // Arrange
        $contributor = $this->contributor('dishes', 'dish');
        $registry = $this->makeRegistry([$contributor], ['dishes']);

        // Act
        $found = $registry->contributorFor('dish');

        // Assert
        self::assertSame($contributor, $found);
        self::assertTrue($registry->has('dish'));
    }

    public function testContributorOfAnInactivePluginIsHidden(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->contributor('dishes', 'dish')], ['books']);

        // Act
        $found = $registry->contributorFor('dish');

        // Assert
        self::assertNull($found);
        self::assertFalse($registry->has('dish'));
        self::assertSame([], $registry->all());
    }

    public function testUnknownItemTypeHasNoContributor(): void
    {
        // Arrange
        $registry = $this->makeRegistry([$this->contributor('dishes', 'dish')], ['dishes']);

        // Act
        $found = $registry->contributorFor('karaoke');

        // Assert
        self::assertNull($found);
    }

    public function testEachActiveContributorKeepsItsOwnItemType(): void
    {
        // Arrange
        $dish = $this->contributor('dishes', 'dish');
        $book = $this->contributor('books', 'book');
        $registry = $this->makeRegistry([$dish, $book], ['dishes', 'books']);

        // Act & Assert
        self::assertSame($dish, $registry->contributorFor('dish'));
        self::assertSame($book, $registry->contributorFor('book'));
        self::assertCount(2, $registry->all());
    }

    /**
     * @param list<ItemPortabilityContributorInterface> $contributors
     * @param list<string>                              $activePlugins
     */
    private function makeRegistry(array $contributors, array $activePlugins): ItemPortabilityRegistry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new ItemPortabilityRegistry($contributors, $pluginService);
    }

    private function contributor(string $pluginKey, string $itemType): ItemPortabilityContributorInterface
    {
        $contributor = $this->createStub(ItemPortabilityContributorInterface::class);
        $contributor->method('getPluginKey')->willReturn($pluginKey);
        $contributor->method('getItemType')->willReturn($itemType);

        return $contributor;
    }
}
