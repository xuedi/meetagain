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
            new AdminLink(label: 'menu_admin_email', route: 'app_admin_email_templates', active: 'email'),
            new AdminLink(label: 'menu_admin_translation', route: 'app_admin_translation', active: 'translation'),
        ];

        // Act
        $config = new AdminNavigationConfig(section: 'System', links: $links);

        // Assert
        $this->assertSame('System', $config->section);
        $this->assertCount(2, $config->links);
        $this->assertSame($links, $config->links);
        $this->assertNull($config->sectionRole);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $link = new AdminLink(label: 'menu_admin_system', route: 'app_admin_system');
        $config = new AdminNavigationConfig(section: 'System', links: [$link]);

        // Assert - readonly class prevents property modification
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - attempt to modify property
        $config->section = 'Modified';
    }
}
