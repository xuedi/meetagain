<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Controller\Admin\AdminNavigationConfig;
use App\Controller\Admin\AdminNavigationInterface;
use App\Entity\AdminLink;
use App\Entity\AdminSection;
use App\Service\AdminNavigationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\Service\AdminNavigationService
 */
#[AllowMockObjectsWithoutExpectations]
final class AdminNavigationServiceTest extends TestCase
{
    private Security $security;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
    }

    public function testGetSidebarSectionsWithControllers(): void
    {
        // Arrange - create mock controller
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    label: 'menu_admin_system',
                    route: 'app_admin_system',
                    active: 'system',
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController], [], __DIR__ . '/../../..');

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertNotEmpty($sections);
        $this->assertContainsOnlyInstancesOf(AdminSection::class, $sections);

        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);
        $this->assertContains('System', $sectionNames);
    }

    public function testGetSidebarSectionsSortsAlphabetically(): void
    {
        // Arrange - create controllers with different section names
        $controllerZ = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Zebra', label: 'menu_zebra', route: 'app_zebra');
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Apple', label: 'menu_apple', route: 'app_apple');
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Mango', label: 'menu_mango', route: 'app_mango');
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$controllerZ, $controllerA, $controllerM],
            [],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - sections should be in alphabetical order
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);

        $this->assertSame(['Apple', 'Mango', 'Zebra'], $sectionNames, 'Sections should be sorted alphabetically');
    }

    public function testGetSidebarSectionsSortsLinksAlphabetically(): void
    {
        // Arrange - create multiple controllers contributing to same section
        $controllerZ = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    label: 'menu_admin_zebra',
                    route: 'app_admin_zebra',
                );
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    label: 'menu_admin_apple',
                    route: 'app_admin_apple',
                );
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    label: 'menu_admin_mango',
                    route: 'app_admin_mango',
                );
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$controllerZ, $controllerA, $controllerM],
            [],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];
        $this->assertSame('System', $systemSection->getSection());

        $linkLabels = array_map(fn(AdminLink $link) => $link->getLabel(), $systemSection->getLinks());
        $this->assertSame(
            ['menu_admin_apple', 'menu_admin_mango', 'menu_admin_zebra'],
            $linkLabels,
            'Links should be sorted alphabetically by label',
        );
    }

    public function testGetSidebarSectionsRespectsRoleRestrictions(): void
    {
        // Arrange - controller with role requirement
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    label: 'menu_admin_system',
                    route: 'app_admin_system',
                    sectionRole: 'ROLE_ADMIN',
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController], [], __DIR__ . '/../../..');

        // Deny ROLE_ADMIN
        $this->security->method('isGranted')->willReturn(false);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should not contain admin-only sections
        $this->assertEmpty($sections, 'System section should be hidden without ROLE_ADMIN');
    }

    public function testControllerReturningNullIsSkipped(): void
    {
        // Arrange - controller that returns null (no navigation)
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return null;
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController], [], __DIR__ . '/../../..');

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should have no sections (YAML file doesn't exist in test context)
        $this->assertEmpty($sections, 'Controllers returning null should not appear in navigation');
    }
}
