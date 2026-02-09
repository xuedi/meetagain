<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Controller\Admin\AdminNavigationConfig
 */
final class AdminNavigationConfigTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $links = [
            new AdminLink(label: 'menu_admin_system', route: 'app_admin_system', active: 'system', role: 'ROLE_ADMIN'),
        ];

        // Act
        $config = new AdminNavigationConfig(section: 'System', links: $links, sectionRole: 'ROLE_META_ADMIN');

        // Assert
        $this->assertSame('System', $config->section);
        $this->assertSame($links, $config->links);
        $this->assertSame('ROLE_META_ADMIN', $config->sectionRole);
    }

    public function testConstructorWithMultipleLinks(): void
    {
        // Arrange
        $links = [
            new AdminLink(label: 'menu_admin_translation', route: 'app_admin_translation', active: 'edit'),
            new AdminLink(
                label: 'menu_admin_translation_extract',
                route: 'app_admin_translation_extract',
                active: 'extract',
            ),
            new AdminLink(
                label: 'menu_admin_translation_publish',
                route: 'app_admin_translation_publish',
                active: 'publish',
            ),
        ];

        // Act
        $config = new AdminNavigationConfig(section: 'Translation', links: $links);

        // Assert
        $this->assertSame('Translation', $config->section);
        $this->assertCount(3, $config->links);
        $this->assertSame($links, $config->links);
        $this->assertNull($config->sectionRole);
    }

    public function testSingleFactoryCreatesConfigWithOneLink(): void
    {
        // Act
        $config = AdminNavigationConfig::single(
            section: 'System',
            label: 'menu_admin_system',
            route: 'app_admin_system',
            active: 'system',
            linkRole: 'ROLE_ADMIN',
            sectionRole: 'ROLE_META_ADMIN',
        );

        // Assert
        $this->assertSame('System', $config->section);
        $this->assertCount(1, $config->links);
        $this->assertSame('ROLE_META_ADMIN', $config->sectionRole);

        $link = $config->links[0];
        $this->assertInstanceOf(AdminLink::class, $link);
        $this->assertSame('menu_admin_system', $link->getLabel());
        $this->assertSame('app_admin_system', $link->getRoute());
        $this->assertSame('system', $link->getActive());
        $this->assertSame('ROLE_ADMIN', $link->getRole());
    }

    public function testSingleFactoryWithOptionalParametersDefaultsToNull(): void
    {
        // Act
        $config = AdminNavigationConfig::single(
            section: 'System',
            label: 'menu_admin_system',
            route: 'app_admin_system',
        );

        // Assert
        $this->assertNull($config->sectionRole);
        $this->assertCount(1, $config->links);

        $link = $config->links[0];
        $this->assertNull($link->getActive());
        $this->assertNull($link->getRole());
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $config = AdminNavigationConfig::single(
            section: 'System',
            label: 'menu_admin_system',
            route: 'app_admin_system',
        );

        // Assert - readonly class prevents property modification
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - attempt to modify property
        $config->section = 'Modified';
    }
}
