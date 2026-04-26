<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\EventTeaser;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Enum\CmsBlock\ImageSupport;
use PHPUnit\Framework\TestCase;

class EventTeaserTest extends TestCase
{
    // --- Arrange / Act / Assert ---

    public function testFromJsonWithFullDataReadsConfiguredFields(): void
    {
        // Arrange
        $json = [
            'headline'   => 'Upcoming events',
            'text'       => 'Join us soon',
            'eventCount' => '6',
            'imageRight' => true,
        ];

        // Act
        $block = EventTeaser::fromJson($json);

        // Assert
        static::assertSame('Upcoming events', $block->headline);
        static::assertSame('Join us soon', $block->text);
        static::assertSame(6, $block->eventCount);
        static::assertTrue($block->imageRight);
        static::assertNull($block->image);
    }

    public function testFromJsonFallsBackToDefaultsWhenKeysMissing(): void
    {
        // Arrange
        $json = ['headline' => 'H', 'text' => 'T'];

        // Act
        $block = EventTeaser::fromJson($json);

        // Assert
        static::assertSame(4, $block->eventCount);
        static::assertFalse($block->imageRight);
    }

    public function testFromJsonClampsEventCountToLowerBound(): void
    {
        // Arrange
        $json = ['headline' => 'H', 'text' => 'T', 'eventCount' => '0'];

        // Act
        $block = EventTeaser::fromJson($json);

        // Assert
        static::assertSame(1, $block->eventCount);
    }

    public function testFromJsonClampsNegativeEventCountToLowerBound(): void
    {
        // Arrange
        $json = ['headline' => 'H', 'text' => 'T', 'eventCount' => '-5'];

        // Act
        $block = EventTeaser::fromJson($json);

        // Assert
        static::assertSame(1, $block->eventCount);
    }

    public function testFromJsonClampsEventCountToUpperBound(): void
    {
        // Arrange
        $json = ['headline' => 'H', 'text' => 'T', 'eventCount' => '9999'];

        // Act
        $block = EventTeaser::fromJson($json);

        // Assert
        static::assertSame(20, $block->eventCount);
    }

    public function testToArrayRoundTripsEventCount(): void
    {
        // Arrange
        $json = [
            'headline'   => 'H',
            'text'       => 'T',
            'eventCount' => '7',
            'imageRight' => false,
        ];

        // Act
        $result = EventTeaser::fromJson($json)->toArray();

        // Assert
        static::assertSame(7, $result['eventCount']);
        static::assertSame('H', $result['headline']);
        static::assertSame('T', $result['text']);
        static::assertFalse($result['imageRight']);
    }

    public function testGetFieldDefinitionsExposesEventCount(): void
    {
        // Arrange / Act
        $fields = EventTeaser::getFieldDefinitions();
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name] = $field;
        }

        // Assert
        static::assertArrayHasKey('eventCount', $byName);
        static::assertSame(FieldType::String, $byName['eventCount']->type);
        static::assertFalse($byName['eventCount']->required);
        static::assertSame('4', $byName['eventCount']->default);
    }

    public function testGetTypeReturnsEventTeaser(): void
    {
        // Arrange / Act / Assert
        static::assertSame(CmsBlockType::EventTeaser, EventTeaser::getType());
    }

    public function testGetCapabilitiesReturnsOptionalImageSupport(): void
    {
        // Arrange / Act
        $caps = EventTeaser::getCapabilities();

        // Assert
        static::assertSame(ImageSupport::Optional, $caps->image);
        static::assertTrue($caps->supportsImageRight);
        static::assertFalse($caps->isGallery);
    }
}
