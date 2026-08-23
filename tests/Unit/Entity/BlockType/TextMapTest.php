<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\TextMap;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\ImageSupport;
use App\Enum\CmsBlock\MapHeight;
use App\Enum\CmsBlock\MapPosition;
use App\Enum\CmsBlock\MapWidth;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextMapTest extends TestCase
{
    public function testFromJsonWithFullData(): void
    {
        // Arrange
        $json = [
            'title' => 'How to find us',
            'content' => '<p>Five minutes from the underground station.</p>',
            'latitude' => '52.5321',
            'longitude' => '13.3799',
            'zoom' => '16',
            'markerLabel' => 'Weiqi Cafe Berlin',
            'mapPosition' => 'left',
            'mapWidth' => 'two_thirds',
            'mapHeight' => 'large',
        ];

        // Act
        $block = TextMap::fromJson($json);

        // Assert
        static::assertSame('How to find us', $block->title);
        static::assertSame('<p>Five minutes from the underground station.</p>', $block->content);
        static::assertSame(52.5321, $block->latitude);
        static::assertSame(13.3799, $block->longitude);
        static::assertSame(16, $block->zoom);
        static::assertSame('Weiqi Cafe Berlin', $block->markerLabel);
        static::assertSame(MapPosition::Left, $block->mapPosition);
        static::assertSame(MapWidth::TwoThirds, $block->mapWidth);
        static::assertSame(MapHeight::Large, $block->mapHeight);
        static::assertTrue($block->hasMap());
    }

    public function testFromJsonWithEmptyDataFallsBackToDefaults(): void
    {
        // Arrange / Act
        $block = TextMap::fromJson([]);

        // Assert
        static::assertSame('', $block->title);
        static::assertSame('', $block->content);
        static::assertNull($block->latitude);
        static::assertNull($block->longitude);
        static::assertSame(TextMap::DEFAULT_ZOOM, $block->zoom);
        static::assertSame(MapPosition::Right, $block->mapPosition);
        static::assertSame(MapWidth::Third, $block->mapWidth);
        static::assertSame(MapHeight::Medium, $block->mapHeight);
        static::assertFalse($block->hasMap());
    }

    #[DataProvider('unusableCoordinateProvider')]
    public function testUnusableCoordinatesDisableTheMap(mixed $latitude, mixed $longitude): void
    {
        // Arrange / Act
        $block = TextMap::fromJson(['content' => '', 'latitude' => $latitude, 'longitude' => $longitude]);

        // Assert
        static::assertFalse($block->hasMap());
    }

    public static function unusableCoordinateProvider(): Generator
    {
        yield 'empty strings' => ['', ''];
        yield 'non-numeric text' => ['north', 'east'];
        yield 'latitude beyond the pole' => ['91.0', '13.3799'];
        yield 'longitude beyond the date line' => ['52.5321', '-180.5'];
        yield 'only a latitude' => ['52.5321', ''];
    }

    #[DataProvider('zoomProvider')]
    public function testZoomIsClamped(mixed $raw, int $expected): void
    {
        // Arrange / Act
        $block = TextMap::fromJson(['content' => '', 'zoom' => $raw]);

        // Assert
        static::assertSame($expected, $block->zoom);
    }

    public static function zoomProvider(): Generator
    {
        yield 'inside the range' => ['12', 12];
        yield 'below the minimum' => ['0', TextMap::MIN_ZOOM];
        yield 'above the maximum' => ['42', TextMap::MAX_ZOOM];
        yield 'not a number' => ['close', TextMap::DEFAULT_ZOOM];
    }

    public function testUnknownLayoutValuesFallBackToDefaults(): void
    {
        // Arrange
        $json = ['content' => '', 'mapPosition' => 'diagonal', 'mapWidth' => 'gigantic', 'mapHeight' => 'tiny'];

        // Act
        $block = TextMap::fromJson($json);

        // Assert
        static::assertSame(MapPosition::Right, $block->mapPosition);
        static::assertSame(MapWidth::Third, $block->mapWidth);
        static::assertSame(MapHeight::Medium, $block->mapHeight);
    }

    public function testToArrayRoundTrip(): void
    {
        // Arrange
        $block = TextMap::fromJson([
            'title' => 'Where we meet',
            'content' => '<p>Ring the bell.</p>',
            'latitude' => '52.5035',
            'longitude' => '13.4052',
            'zoom' => '17',
            'markerLabel' => 'Community Center Mitte',
            'mapPosition' => 'above',
            'mapWidth' => 'half',
            'mapHeight' => 'small',
        ]);

        // Act
        $stored = $block->toArray();

        // Assert
        static::assertSame([
            'title' => 'Where we meet',
            'content' => '<p>Ring the bell.</p>',
            'latitude' => 52.5035,
            'longitude' => 13.4052,
            'zoom' => 17,
            'markerLabel' => 'Community Center Mitte',
            'mapPosition' => 'above',
            'mapWidth' => 'half',
            'mapHeight' => 'small',
        ], $stored);
        static::assertEquals($block, TextMap::fromJson($stored));
    }

    public function testBlockMetadata(): void
    {
        // Arrange / Act
        $capabilities = TextMap::getCapabilities();

        // Assert
        static::assertSame(CmsBlockType::TextMap, TextMap::getType());
        static::assertSame(ImageSupport::None, $capabilities->image);
        static::assertFalse($capabilities->supportsImageRight);
        static::assertFalse($capabilities->isGallery);
    }

    public function testContentIsTheOnlyRequiredField(): void
    {
        // Arrange / Act
        $required = array_values(array_map(
            static fn($field) => $field->name,
            array_filter(TextMap::getFieldDefinitions(), static fn($field) => $field->required),
        ));

        // Assert
        static::assertSame(['content'], $required);
    }
}
