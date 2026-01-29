<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Dashboard\DashboardCenterTileInterface;
use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Service\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    public function testGetCenterTilesFiltersAndSorts(): void
    {
        // Arrange: Create mock tiles with different priorities
        $tile1 = $this->createCenterTile('tile1', 100, true);
        $tile2 = $this->createCenterTile('tile2', 50, true);
        $tile3 = $this->createCenterTile('tile3', 75, false); // Not accessible

        $service = new DashboardService([$tile1, $tile2, $tile3], []);

        $user = $this->createMock(User::class);

        // Act: Get center tiles for user
        $result = $service->getCenterTilesForUser($user, null);

        // Assert: Only accessible tiles, sorted by priority (high to low)
        $this->assertCount(2, $result, 'Should return only accessible tiles');
        $this->assertSame('tile1', $result[0]->getKey(), 'Highest priority tile should be first');
        $this->assertSame('tile2', $result[1]->getKey(), 'Lower priority tile should be second');
    }

    public function testGetSideTilesFiltersAndSorts(): void
    {
        // Arrange: Create mock side tiles
        $tile1 = $this->createSideTile('side1', 80, true);
        $tile2 = $this->createSideTile('side2', 90, true);
        $tile3 = $this->createSideTile('side3', 85, false); // Not accessible

        $service = new DashboardService([], [$tile1, $tile2, $tile3]);

        $user = $this->createMock(User::class);

        // Act: Get side tiles for user
        $result = $service->getSideTilesForUser($user, null);

        // Assert: Only accessible tiles, sorted by priority
        $this->assertCount(2, $result);
        $this->assertSame('side2', $result[0]->getKey(), 'Priority 90 should be first');
        $this->assertSame('side1', $result[1]->getKey(), 'Priority 80 should be second');
    }

    public function testEmptyTilesWhenNoneAccessible(): void
    {
        // Arrange: Create tiles that are all inaccessible
        $tile1 = $this->createCenterTile('tile1', 100, false);
        $tile2 = $this->createSideTile('side1', 80, false);

        $service = new DashboardService([$tile1], [$tile2]);

        $user = $this->createMock(User::class);

        // Act: Get tiles
        $centerTiles = $service->getCenterTilesForUser($user, null);
        $sideTiles = $service->getSideTilesForUser($user, null);

        // Assert: No tiles returned
        $this->assertEmpty($centerTiles, 'Should return empty array when no center tiles accessible');
        $this->assertEmpty($sideTiles, 'Should return empty array when no side tiles accessible');
    }

    private function createCenterTile(string $key, int $priority, bool $accessible): DashboardCenterTileInterface
    {
        $tile = $this->createMock(DashboardCenterTileInterface::class);
        $tile->method('getKey')->willReturn($key);
        $tile->method('getPriority')->willReturn($priority);
        $tile->method('isAccessible')->willReturn($accessible);
        return $tile;
    }

    private function createSideTile(string $key, int $priority, bool $accessible): DashboardSideTileInterface
    {
        $tile = $this->createMock(DashboardSideTileInterface::class);
        $tile->method('getKey')->willReturn($key);
        $tile->method('getPriority')->willReturn($priority);
        $tile->method('isAccessible')->willReturn($accessible);
        return $tile;
    }
}
