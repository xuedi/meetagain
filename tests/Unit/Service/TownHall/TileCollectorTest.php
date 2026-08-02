<?php declare(strict_types=1);

namespace Tests\Unit\Service\TownHall;

use App\Enum\TownHallTileLocation;
use App\Service\TownHall\Tile;
use App\Service\TownHall\TileCollector;
use App\Service\TownHall\TileProviderInterface;
use PHPUnit\Framework\TestCase;

class TileCollectorTest extends TestCase
{
    public function testAnEmptyChainContributesNothing(): void
    {
        // Arrange
        $collector = new TileCollector([]);

        // Act
        $result = $collector->collectByLocation();

        // Assert
        self::assertSame([], $result);
    }

    public function testADecliningProviderContributesNothing(): void
    {
        // Arrange
        $collector = new TileCollector([
            $this->provider(null),
            $this->provider($this->tile('a.html.twig', TownHallTileLocation::Sidebar)),
        ]);

        // Act
        $result = $collector->collectByLocation();

        // Assert
        self::assertCount(1, $result['sidebar']);
        self::assertSame('a.html.twig', $result['sidebar'][0]->partial);
    }

    public function testTilesAreSortedByDescendingPriorityWithinTheirLocation(): void
    {
        // Arrange
        $collector = new TileCollector([
            $this->provider($this->tile('low.html.twig', TownHallTileLocation::Sidebar, 10)),
            $this->provider($this->tile('high.html.twig', TownHallTileLocation::Sidebar, 90)),
            $this->provider($this->tile('mid.html.twig', TownHallTileLocation::Sidebar, 50)),
        ]);

        // Act
        $result = $collector->collectByLocation();

        // Assert
        self::assertSame(
            ['high.html.twig', 'mid.html.twig', 'low.html.twig'],
            array_map(static fn(Tile $tile): string => $tile->partial, $result['sidebar']),
        );
    }

    public function testTilesAreGroupedByLocation(): void
    {
        // Arrange
        $collector = new TileCollector([
            $this->provider($this->tile('side.html.twig', TownHallTileLocation::Sidebar)),
            $this->provider($this->tile('main.html.twig', TownHallTileLocation::Main)),
            $this->provider($this->tile('wide.html.twig', TownHallTileLocation::FullWidth)),
        ]);

        // Act
        $result = $collector->collectByLocation();

        // Assert
        self::assertSame(['sidebar', 'main', 'fullWidth'], array_keys($result));
        self::assertSame('main.html.twig', $result['main'][0]->partial);
    }

    private function provider(?Tile $tile): TileProviderInterface
    {
        $provider = $this->createStub(TileProviderInterface::class);
        $provider->method('provide')->willReturn($tile);

        return $provider;
    }

    private function tile(string $partial, TownHallTileLocation $location, int $priority = 0): Tile
    {
        return new Tile($partial, ['key' => 'value'], $location, $priority);
    }
}
