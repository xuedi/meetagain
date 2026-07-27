<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\FacetSelection;
use PHPUnit\Framework\TestCase;

class FacetSelectionTest extends TestCase
{
    public function testEmptySelectionCarriesNothing(): void
    {
        // Arrange
        $selection = new FacetSelection();

        // Act + Assert
        static::assertTrue($selection->isEmpty());
        static::assertSame(0, $selection->count());
        static::assertSame([], $selection->toQuery());
    }

    public function testCategoryReplacesThePreviousOne(): void
    {
        // Arrange
        $selection = new FacetSelection(3, [7]);

        // Act
        $changed = $selection->withCategory(5);

        // Assert
        static::assertSame(5, $changed->category);
        static::assertSame([7], $changed->tags);
        static::assertSame(3, $selection->category);
    }

    public function testCategoryClearsWithNull(): void
    {
        // Arrange
        $selection = new FacetSelection(3);

        // Act
        $changed = $selection->withCategory(null);

        // Assert
        static::assertNull($changed->category);
        static::assertTrue($changed->isEmpty());
    }

    public function testTagToggleAddsThenRemoves(): void
    {
        // Arrange
        $selection = new FacetSelection();

        // Act
        $added = $selection->withTagToggled(4);
        $removed = $added->withTagToggled(4);

        // Assert
        static::assertSame([4], $added->tags);
        static::assertTrue($added->hasTag(4));
        static::assertSame([], $removed->tags);
        static::assertFalse($removed->hasTag(4));
    }

    public function testCountAddsCategoryAndTags(): void
    {
        // Arrange
        $selection = new FacetSelection(3, [7, 9]);

        // Act + Assert
        static::assertSame(3, $selection->count());
        static::assertFalse($selection->isEmpty());
    }

    public function testQueryShapeMatchesTheFacetUrlParameters(): void
    {
        // Arrange
        $selection = new FacetSelection(3, [7, 9]);

        // Act
        $query = $selection->toQuery();

        // Assert
        static::assertSame(['category' => 3, 'tag' => [7, 9]], $query);
    }

    public function testQueryOmitsAnAbsentFacet(): void
    {
        // Arrange
        $selection = new FacetSelection(null, [7]);

        // Act + Assert
        static::assertSame(['tag' => [7]], $selection->toQuery());
        static::assertSame(['category' => 3], new FacetSelection(3)->toQuery());
    }
}
