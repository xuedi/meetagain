<?php declare(strict_types=1);

namespace Tests\Unit\Template;

use App\Enum\EventTileLocation;
use PHPUnit\Framework\TestCase;

class EventDetailsTileSeamTest extends TestCase
{
    private const string TEMPLATE = __DIR__ . '/../../../templates/events/details.html.twig';

    public function testEveryTileLocationHasARenderSlot(): void
    {
        // Arrange
        $details = $this->details();
        $sidepanel = (string) file_get_contents(__DIR__ . '/../../../templates/events/details/sidepanel.html.twig');

        // Act
        $slots = $details . $sidepanel;

        // Assert
        static::assertStringContainsString('pluginTiles', $slots);
        static::assertStringContainsString('pluginCenterTiles', $slots);
        static::assertStringContainsString('pluginBottomSidebarTiles', $slots);
        static::assertCount(3, EventTileLocation::cases());
    }

    public function testCenterTilesRenderInsideTheWideColumnAheadOfTheRsvpBox(): void
    {
        // Arrange
        $details = $this->details();

        // Act
        $columnStart = strpos($details, 'column is-9');
        $centerLoop = strpos($details, 'pluginCenterTiles');
        $rsvpBox = strpos($details, 'events/details/rsvp.html.twig');

        // Assert
        static::assertIsInt($columnStart);
        static::assertIsInt($centerLoop);
        static::assertIsInt($rsvpBox);
        static::assertGreaterThan($columnStart, $centerLoop);
        static::assertLessThan($rsvpBox, $centerLoop);
    }

    public function testCenterTilesAreGatedBehindTheMemberCheck(): void
    {
        // Arrange
        $details = $this->details();

        // Act
        $memberGate = strpos($details, "is_granted('ROLE_USER')");
        $centerLoop = strpos($details, 'pluginCenterTiles');

        // Assert
        static::assertIsInt($memberGate);
        static::assertIsInt($centerLoop);
        static::assertGreaterThan($memberGate, $centerLoop);
    }

    private function details(): string
    {
        return (string) file_get_contents(self::TEMPLATE);
    }
}
