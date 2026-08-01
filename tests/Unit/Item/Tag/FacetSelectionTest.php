<?php declare(strict_types=1);

namespace Tests\Unit\Item\Tag;

use App\Item\Tag\FacetSelection;
use PHPUnit\Framework\TestCase;

class FacetSelectionTest extends TestCase
{
    public function testTogglingAddsThenRemovesTheSameTag(): void
    {
        // Arrange
        $selection = new FacetSelection();

        // Act
        $added = $selection->withTagToggled(4);
        $removed = $added->withTagToggled(4);

        // Assert
        self::assertTrue($added->hasTag(4));
        self::assertFalse($removed->hasTag(4));
        self::assertTrue($removed->isEmpty());
    }

    public function testQueryCarriesEveryTagAndNothingElse(): void
    {
        // Arrange
        $selection = new FacetSelection([4, 9]);

        // Act
        $query = $selection->toQuery();

        // Assert
        self::assertSame(['tag' => [4, 9]], $query);
        self::assertSame(2, $selection->count());
    }

    public function testAnEmptySelectionProducesNoQuery(): void
    {
        // Arrange + Act
        $selection = new FacetSelection();

        // Assert
        self::assertSame([], $selection->toQuery());
        self::assertSame(0, $selection->count());
    }
}
