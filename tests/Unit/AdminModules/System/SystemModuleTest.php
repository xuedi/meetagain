<?php declare(strict_types=1);

namespace Tests\Unit\AdminModules\System;

use App\AdminModules\System\SystemModule;
use App\Entity\User;
use App\Entity\UserRole;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SystemModuleTest extends TestCase
{
    public function testGetKey(): void
    {
        // Arrange
        $security = $this->createMock(Security::class);
        $module = new SystemModule($security);

        // Act
        $key = $module->getKey();

        // Assert
        $this->assertSame('system', $key);
    }

    public function testGetPriority(): void
    {
        // Arrange
        $security = $this->createMock(Security::class);
        $module = new SystemModule($security);

        // Act
        $priority = $module->getPriority();

        // Assert
        $this->assertSame(1000, $priority);
    }

    public function testGetSectionName(): void
    {
        // Arrange
        $security = $this->createMock(Security::class);
        $module = new SystemModule($security);

        // Act
        $sectionName = $module->getSectionName();

        // Assert
        $this->assertSame('System', $sectionName);
    }

    public function testGetLinks(): void
    {
        // Arrange
        $security = $this->createMock(Security::class);
        $module = new SystemModule($security);

        // Act
        $links = $module->getLinks();

        // Assert
        $this->assertCount(1, $links);
        $this->assertSame('menu_admin_system', $links[0]->getLabel());
        $this->assertSame('app_admin_system', $links[0]->getRoute());
        $this->assertSame('system', $links[0]->getActive());
    }

    public function testGetRoutes(): void
    {
        // Arrange
        $security = $this->createMock(Security::class);
        $module = new SystemModule($security);

        // Act
        $routes = $module->getRoutes();

        // Assert
        $this->assertCount(4, $routes);
        $this->assertSame('app_admin_system', $routes[0]['name']);
        $this->assertSame('/admin/system', $routes[0]['path']);
        $this->assertSame('app_admin_regenerate_thumbnails', $routes[1]['name']);
        $this->assertSame('app_admin_cleanup_thumbnails', $routes[2]['name']);
        $this->assertSame('app_admin_system_boolean', $routes[3]['name']);
    }

    public function testIsAccessibleWithAdminRole(): void
    {
        // Arrange: Create admin user
        $user = $this->createMock(User::class);
        $user->method('hasUserRole')->with(UserRole::Admin)->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $module = new SystemModule($security);

        // Act
        $isAccessible = $module->isAccessible();

        // Assert
        $this->assertTrue($isAccessible);
    }

    public function testIsNotAccessibleWithoutAdminRole(): void
    {
        // Arrange: Create regular user
        $user = $this->createMock(User::class);
        $user->method('hasUserRole')->with(UserRole::Admin)->willReturn(false);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $module = new SystemModule($security);

        // Act
        $isAccessible = $module->isAccessible();

        // Assert
        $this->assertFalse($isAccessible);
    }
}
