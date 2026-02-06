<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\AdminNavigationConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Controller\Admin\AdminNavigationConfig
 */
final class AdminNavigationConfigTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        // Arrange & Act
        $config = new AdminNavigationConfig(
            section: 'System',
            label: 'menu_admin_system',
            route: 'app_admin_system',
            active: 'system',
            linkRole: 'ROLE_ADMIN',
            sectionRole: 'ROLE_META_ADMIN',
        );

        // Assert
        $this->assertSame('System', $config->section);
        $this->assertSame('menu_admin_system', $config->label);
        $this->assertSame('app_admin_system', $config->route);
        $this->assertSame('system', $config->active);
        $this->assertSame('ROLE_ADMIN', $config->linkRole);
        $this->assertSame('ROLE_META_ADMIN', $config->sectionRole);
    }

    public function testOptionalParametersDefaultToNull(): void
    {
        // Arrange & Act
        $config = new AdminNavigationConfig(section: 'System', label: 'menu_admin_system', route: 'app_admin_system');

        // Assert
        $this->assertNull($config->active);
        $this->assertNull($config->linkRole);
        $this->assertNull($config->sectionRole);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $config = new AdminNavigationConfig(section: 'System', label: 'menu_admin_system', route: 'app_admin_system');

        // Assert - readonly class prevents property modification
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - attempt to modify property
        $config->section = 'Modified';
    }
}
