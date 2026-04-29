<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\FactsRow;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\ImageSupport;
use PHPUnit\Framework\TestCase;

class FactsRowTest extends TestCase
{
    public function testFromJsonWithFullData(): void
    {
        // Arrange
        $json = [
            'headline' => 'Why play with us',
            'facts' => [
                ['icon' => 'fa fa-users', 'label' => 'Active community'],
                ['icon' => 'fa fa-globe', 'label' => 'Players worldwide'],
                ['icon' => 'fa fa-graduation-cap', 'label' => 'Beginner-friendly'],
                ['icon' => 'fa fa-trophy', 'label' => 'Regular tournaments'],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertSame('Why play with us', $block->headline);
        static::assertCount(4, $block->facts);
        static::assertSame('fa fa-users', $block->facts[0]['icon']);
        static::assertSame('Active community', $block->facts[0]['label']);
        static::assertSame('fa fa-trophy', $block->facts[3]['icon']);
    }

    public function testFromJsonWithEmptyDataProducesEmptyFacts(): void
    {
        // Arrange / Act
        $block = FactsRow::fromJson([]);

        // Assert
        static::assertSame('', $block->headline);
        static::assertSame([], $block->facts);
    }

    public function testFromJsonTruncatesBeyondMaxFacts(): void
    {
        // Arrange
        $json = [
            'facts' => array_map(
                static fn(int $i): array => ['icon' => 'fa fa-' . $i, 'label' => 'Fact ' . $i],
                range(1, 9),
            ),
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertCount(FactsRow::MAX_FACTS, $block->facts);
        static::assertSame('fa fa-1', $block->facts[0]['icon']);
        static::assertSame('fa fa-6', $block->facts[5]['icon']);
    }

    public function testFromJsonDropsRowsWhereBothFieldsAreBlank(): void
    {
        // Arrange
        $json = [
            'facts' => [
                ['icon' => 'fa fa-users', 'label' => 'Real fact'],
                ['icon' => '', 'label' => ''],
                ['icon' => '   ', 'label' => "\t"],
                ['icon' => 'fa fa-globe', 'label' => 'Another'],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertCount(2, $block->facts);
        static::assertSame('fa fa-users', $block->facts[0]['icon']);
        static::assertSame('fa fa-globe', $block->facts[1]['icon']);
    }

    public function testFromJsonKeepsRowsWithOnlyOneFieldFilled(): void
    {
        // Arrange
        $json = [
            'facts' => [
                ['icon' => 'fa fa-users', 'label' => ''],
                ['icon' => '', 'label' => 'Label only'],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertCount(2, $block->facts);
        static::assertSame('fa fa-users', $block->facts[0]['icon']);
        static::assertSame('', $block->facts[0]['label']);
        static::assertSame('', $block->facts[1]['icon']);
        static::assertSame('Label only', $block->facts[1]['label']);
    }

    public function testFromJsonTrimsWhitespace(): void
    {
        // Arrange
        $json = [
            'headline' => '  Trimmed headline  ',
            'facts' => [
                ['icon' => '  fa fa-users  ', 'label' => "  Active community\n"],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertSame('Trimmed headline', $block->headline);
        static::assertSame('fa fa-users', $block->facts[0]['icon']);
        static::assertSame('Active community', $block->facts[0]['label']);
    }

    public function testFromJsonIgnoresNonArrayFactRows(): void
    {
        // Arrange
        $json = [
            'facts' => [
                'not-an-array',
                42,
                ['icon' => 'fa fa-users', 'label' => 'Real fact'],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertCount(1, $block->facts);
        static::assertSame('fa fa-users', $block->facts[0]['icon']);
    }

    public function testFromJsonIgnoresNonArrayFactsKey(): void
    {
        // Arrange
        $json = [
            'headline' => 'Hi',
            'facts' => 'not-an-array',
        ];

        // Act
        $block = FactsRow::fromJson($json);

        // Assert
        static::assertSame('Hi', $block->headline);
        static::assertSame([], $block->facts);
    }

    public function testToArrayRoundTrip(): void
    {
        // Arrange
        $json = [
            'headline' => 'Test',
            'facts' => [
                ['icon' => 'fa fa-users', 'label' => 'A'],
                ['icon' => 'fa fa-globe', 'label' => 'B'],
            ],
        ];

        // Act
        $block = FactsRow::fromJson($json);
        $result = $block->toArray();

        // Assert
        static::assertSame('Test', $result['headline']);
        static::assertCount(2, $result['facts']);
        static::assertSame('fa fa-users', $result['facts'][0]['icon']);
        static::assertSame('A', $result['facts'][0]['label']);
        static::assertSame('fa fa-globe', $result['facts'][1]['icon']);
    }

    public function testGetTypeReturnsFactsRow(): void
    {
        // Arrange / Act / Assert
        static::assertSame(CmsBlockType::FactsRow, FactsRow::getType());
    }

    public function testGetCapabilitiesReturnsNoneImageSupport(): void
    {
        // Arrange / Act
        $caps = FactsRow::getCapabilities();

        // Assert
        static::assertSame(ImageSupport::None, $caps->image);
        static::assertFalse($caps->supportsImageRight);
        static::assertFalse($caps->isGallery);
    }
}
