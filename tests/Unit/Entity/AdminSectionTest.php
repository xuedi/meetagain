<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AdminLink;
use App\Entity\AdminSection;
use PHPUnit\Framework\TestCase;

class AdminSectionTest extends TestCase
{
    public function testGetRoleReturnsNullWhenNotSet(): void
    {
        // Arrange
        $links = [
            new AdminLink('Dashboard', 'app_admin_dashboard', 'dashboard'),
        ];

        // Act
        $section = new AdminSection('Admin', $links);

        // Assert
        static::assertNull($section->getRole());
    }

    public function testGetRoleReturnsSpecifiedRole(): void
    {
        // Arrange
        $links = [
            new AdminLink('Settings', 'app_admin_settings', 'settings'),
        ];

        // Act
        $section = new AdminSection('Settings', $links, 'ROLE_ADMIN');

        // Assert
        static::assertSame('ROLE_ADMIN', $section->getRole());
    }

    public function testConstructorAcceptsAllParameters(): void
    {
        // Arrange
        $links = [
            new AdminLink('Users', 'app_admin_users', 'users'),
            new AdminLink('Groups', 'app_admin_groups', 'groups'),
        ];

        // Act
        $section = new AdminSection('User Management', $links, 'ROLE_META_ADMIN');

        // Assert
        static::assertSame('User Management', $section->getSection());
        static::assertSame($links, $section->getLinks());
        static::assertSame('ROLE_META_ADMIN', $section->getRole());
    }
}
