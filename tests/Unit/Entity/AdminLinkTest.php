<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AdminLink;
use PHPUnit\Framework\TestCase;

class AdminLinkTest extends TestCase
{
    public function testGetRoleReturnsNullWhenNotSet(): void
    {
        // Arrange & Act
        $link = new AdminLink('Dashboard', 'app_admin_dashboard', 'dashboard');

        // Assert
        $this->assertNull($link->getRole());
    }

    public function testGetRoleReturnsSpecifiedRole(): void
    {
        // Arrange & Act
        $link = new AdminLink('Settings', 'app_admin_settings', 'settings', 'ROLE_ADMIN');

        // Assert
        $this->assertSame('ROLE_ADMIN', $link->getRole());
    }

    public function testConstructorAcceptsAllParameters(): void
    {
        // Arrange & Act
        $link = new AdminLink('Users', 'app_admin_users', 'users', 'ROLE_META_ADMIN');

        // Assert
        $this->assertSame('Users', $link->getLabel());
        $this->assertSame('app_admin_users', $link->getRoute());
        $this->assertSame('users', $link->getActive());
        $this->assertSame('ROLE_META_ADMIN', $link->getRole());
    }
}
