<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Item;

use App\Portability\Item\ContributorInterface;
use App\Portability\Item\Registry;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
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

    public function testTheContributorsFollowTheActivePluginsWhenTheyChangeBetweenCalls(): void
    {
        // Arrange
        $film = $this->contributor('films', 'film');
        $book = $this->contributor('books', 'book');
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturnOnConsecutiveCalls(['films'], ['books'], ['films']);
        $registry = new Registry([$film, $book], $pluginService);

        // Act
        $first = $registry->all();
        $second = $registry->all();
        $third = $registry->all();

        // Assert
        self::assertSame([$film], $first);
        self::assertSame([$book], $second);
        self::assertSame([$film], $third);
    }

    /**
     * @param list<ContributorInterface> $contributors
     * @param list<string>                              $activePlugins
     */
    private function makeRegistry(array $contributors, array $activePlugins): Registry
    {
        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new Registry($contributors, $pluginService);
    }

    private function contributor(string $pluginKey, string $itemType): ContributorInterface
    {
        $contributor = $this->createStub(ContributorInterface::class);
        $contributor->method('getPluginKey')->willReturn($pluginKey);
        $contributor->method('getItemType')->willReturn($itemType);

        return $contributor;
    }
}
