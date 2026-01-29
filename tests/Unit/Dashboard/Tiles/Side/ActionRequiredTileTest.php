<?php declare(strict_types=1);

namespace Tests\Unit\Dashboard\Tiles\Side;

use App\Dashboard\Tiles\Side\ActionRequiredTile;
use App\Entity\User;
use App\Entity\UserRole;
use App\Service\DashboardActionService;
use PHPUnit\Framework\TestCase;

class ActionRequiredTileTest extends TestCase
{
    private DashboardActionService $actionService;
    private ActionRequiredTile $tile;

    protected function setUp(): void
    {
        // Arrange: Create mock service
        $this->actionService = $this->createMock(DashboardActionService::class);
        $this->tile = new ActionRequiredTile($this->actionService);
    }

    public function testGetKey(): void
    {
        // Act & Assert
        $this->assertSame('action_required', $this->tile->getKey());
    }

    public function testGetPriority(): void
    {
        // Act & Assert
        $this->assertSame(100, $this->tile->getPriority(), 'Action Required should have highest priority (100)');
    }

    public function testGetTemplate(): void
    {
        // Act & Assert
        $this->assertSame('admin/tiles/side/action_required.html.twig', $this->tile->getTemplate());
    }

    public function testIsAccessibleOnlyForAdmin(): void
    {
        // Arrange: Create admin user
        $adminUser = $this->createMock(User::class);
        $adminUser->method('hasUserRole')->with(UserRole::Admin)->willReturn(true);

        // Act & Assert: Admin can access
        $this->assertTrue($this->tile->isAccessible($adminUser, null), 'Admin should have access');

        // Arrange: Create regular user
        $regularUser = $this->createMock(User::class);
        $regularUser->method('hasUserRole')->with(UserRole::Admin)->willReturn(false);

        // Act & Assert: Regular user cannot access
        $this->assertFalse($this->tile->isAccessible($regularUser, null), 'Regular user should not have access');
    }

    public function testGetDataReturnsCorrectStructure(): void
    {
        // Arrange: Mock service responses
        $this->actionService
            ->method('getActionItems')
            ->willReturn([
                'reportedImages' => 3,
                'pendingTranslations' => 5,
                'staleEmails' => 2,
                'pendingEmails' => 10,
            ]);
        $this->actionService->method('getUnverifiedCount')->willReturn(7);

        $user = $this->createMock(User::class);

        // Act: Get tile data
        $data = $this->tile->getData($user, null);

        // Assert: Data structure is correct
        $this->assertArrayHasKey('actionItems', $data);
        $this->assertArrayHasKey('unverifiedCount', $data);
        $this->assertSame(3, $data['actionItems']['reportedImages']);
        $this->assertSame(7, $data['unverifiedCount']);
    }

    public function testGroupParameterNotUsed(): void
    {
        // Arrange: Mock service
        $this->actionService
            ->method('getActionItems')
            ->willReturn([
                'reportedImages' => 0,
                'pendingTranslations' => 0,
                'staleEmails' => 0,
                'pendingEmails' => 0,
            ]);
        $this->actionService->method('getUnverifiedCount')->willReturn(0);

        $user = $this->createMock(User::class);
        $group = new \stdClass();

        // Act: Get data with group (should be ignored)
        $dataWithoutGroup = $this->tile->getData($user, null);
        $dataWithGroup = $this->tile->getData($user, $group);

        // Assert: Group parameter doesn't change behavior for admin-only tile
        $this->assertEquals($dataWithoutGroup, $dataWithGroup, 'Group parameter should not affect admin-only tile');
    }
}
