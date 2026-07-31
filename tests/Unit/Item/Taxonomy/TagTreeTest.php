<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\TagDefinition;
use App\Item\Taxonomy\TagTree;
use PHPUnit\Framework\TestCase;

class TagTreeTest extends TestCase
{
    public function testAnEmptyVocabularyHasNoBranches(): void
    {
        // Arrange
        $tree = new TagTree([]);

        // Act + Assert
        static::assertFalse($tree->hasBranches());
        static::assertSame([], $tree->ordered());
        static::assertSame([], $tree->children(null));
    }

    public function testAncestorsRunFromTheNearestParentToTheRoot(): void
    {
        // Arrange
        $tree = $this->tree();

        // Act + Assert
        static::assertSame([2, 1], $tree->ancestors(3));
        static::assertSame(3, $tree->depthOf(3));
        static::assertSame(1, $tree->root(3)?->id);
        static::assertNull($tree->root(1));
        static::assertSame(2, $tree->parent(3));
    }

    public function testOrderedKeepsEveryTagDirectlyBelowItsParent(): void
    {
        // Arrange
        $tree = $this->tree();

        // Act + Assert
        static::assertSame([1, 2, 3, 4], array_column($tree->ordered(), 'id'));
        static::assertSame([2], array_column($tree->children(1), 'id'));
    }

    public function testACycleThatNormalizeNeverSawStillYieldsEveryTagExactlyOnce(): void
    {
        // Arrange
        $tree = new TagTree([
            new TagDefinition(1, ['en' => 'A'], 2),
            new TagDefinition(2, ['en' => 'B'], 1),
            new TagDefinition(3, ['en' => 'C']),
        ]);

        // Act
        $ordered = $tree->ordered();

        // Assert
        static::assertSame([3, 1, 2], array_column($ordered, 'id'));
        static::assertSame([2], $tree->ancestors(1), 'The walk up stops as soon as it revisits a tag');
    }

    public function testLeafmostDropsAnAncestorTheClosureCarriedAlong(): void
    {
        // Arrange
        $tree = $this->tree();

        // Act + Assert
        static::assertSame([3, 4], $tree->leafmost([1, 2, 3, 4]));
        static::assertSame([1, 4], $tree->leafmost([1, 4]), 'A root assigned on its own stays');
        static::assertSame([], $tree->leafmost([]));
    }

    private function tree(): TagTree
    {
        return new TagTree([
            new TagDefinition(1, ['en' => 'Meat']),
            new TagDefinition(2, ['en' => 'Poultry'], 1),
            new TagDefinition(3, ['en' => 'Chicken'], 2),
            new TagDefinition(4, ['en' => 'Fish']),
        ]);
    }
}
