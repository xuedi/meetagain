<?php declare(strict_types=1);

namespace Tests\Unit\Enum\CmsBlock;

use App\Enum\CmsBlock\MapHeight;
use App\Enum\CmsBlock\MapPosition;
use App\Enum\CmsBlock\MapWidth;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MapLayoutTest extends TestCase
{
    #[DataProvider('positionProvider')]
    public function testStackedPositions(MapPosition $position, bool $expected): void
    {
        // Arrange / Act / Assert
        static::assertSame($expected, $position->isStacked());
    }

    public static function positionProvider(): Generator
    {
        yield 'left sits beside the text' => [MapPosition::Left, false];
        yield 'right sits beside the text' => [MapPosition::Right, false];
        yield 'above stacks' => [MapPosition::Above, true];
        yield 'below stacks' => [MapPosition::Below, true];
    }

    #[DataProvider('widthProvider')]
    public function testColumnClass(MapWidth $width, string $expected): void
    {
        // Arrange / Act / Assert
        static::assertSame($expected, $width->columnClass());
    }

    public static function widthProvider(): Generator
    {
        yield 'a third of twelve columns' => [MapWidth::Third, 'is-4'];
        yield 'half of twelve columns' => [MapWidth::Half, 'is-6'];
        yield 'two thirds of twelve columns' => [MapWidth::TwoThirds, 'is-8'];
    }

    #[DataProvider('heightProvider')]
    public function testPixels(MapHeight $height, string $expected): void
    {
        // Arrange / Act / Assert
        static::assertSame($expected, $height->pixels());
    }

    public static function heightProvider(): Generator
    {
        yield 'small' => [MapHeight::Small, '200px'];
        yield 'medium' => [MapHeight::Medium, '320px'];
        yield 'large' => [MapHeight::Large, '480px'];
    }
}
