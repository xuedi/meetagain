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
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_system',
                    route: 'app_admin_system',
                    active: 'system',
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController]);

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
                return AdminNavigationConfig::single(section: 'Zebra', label: 'menu_zebra', route: 'app_zebra');
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return AdminNavigationConfig::single(section: 'Apple', label: 'menu_apple', route: 'app_apple');
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return AdminNavigationConfig::single(section: 'Mango', label: 'menu_mango', route: 'app_mango');
            }
        };

        $service = new AdminNavigationService($this->security, [$controllerZ, $controllerA, $controllerM]);

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
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_zebra',
                    route: 'app_admin_zebra',
                );
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_apple',
                    route: 'app_admin_apple',
                );
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_mango',
                    route: 'app_admin_mango',
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$controllerZ, $controllerA, $controllerM]);

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
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_system',
                    route: 'app_admin_system',
                    sectionRole: 'ROLE_ADMIN',
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController]);

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

        $service = new AdminNavigationService($this->security, [$mockController]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should have no sections
        $this->assertEmpty($sections, 'Controllers returning null should not appear in navigation');
    }

    public function testGetSidebarSectionsHandlesMultipleLinksPerController(): void
    {
        // Arrange - controller with multiple links (like TranslationController)
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Translation', links: [
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
                ]);
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections, 'Should have one section');
        $translationSection = $sections[0];
        $this->assertSame('Translation', $translationSection->getSection());

        $links = $translationSection->getLinks();
        $this->assertCount(3, $links, 'Should have three links from the same controller');

        $linkLabels = array_map(fn(AdminLink $link) => $link->getLabel(), $links);
        $this->assertSame(
            ['menu_admin_translation', 'menu_admin_translation_extract', 'menu_admin_translation_publish'],
            $linkLabels,
            'All three links should be present',
        );
    }

    public function testMultiLinkControllerLinksSortedAlphabeticallyWithOtherControllers(): void
    {
        // Arrange - mix of single-link and multi-link controllers in same section
        $singleLinkController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return AdminNavigationConfig::single(
                    section: 'System',
                    label: 'menu_admin_banana',
                    route: 'app_admin_banana',
                );
            }
        };

        $multiLinkController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_zebra', route: 'app_admin_zebra'),
                    new AdminLink(label: 'menu_admin_apple', route: 'app_admin_apple'),
                ]);
            }
        };

        $service = new AdminNavigationService($this->security, [$singleLinkController, $multiLinkController]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];

        $linkLabels = array_map(fn(AdminLink $link) => $link->getLabel(), $systemSection->getLinks());
        $this->assertSame(
            ['menu_admin_apple', 'menu_admin_banana', 'menu_admin_zebra'],
            $linkLabels,
            'Links from both single and multi-link controllers should be sorted alphabetically',
        );
    }

    public function testMultiLinkControllerWithDifferentRolesPerLink(): void
    {
        // Arrange - multi-link controller with different role requirements per link
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_public', route: 'app_admin_public'),
                    new AdminLink(
                        label: 'menu_admin_restricted',
                        route: 'app_admin_restricted',
                        role: 'ROLE_SUPER_ADMIN',
                    ),
                ]);
            }
        };

        $service = new AdminNavigationService($this->security, [$mockController]);

        // Only grant base admin role, not ROLE_SUPER_ADMIN
        $this->security->method('isGranted')->willReturnCallback(fn(string $role) => $role !== 'ROLE_SUPER_ADMIN');

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];

        $links = $systemSection->getLinks();
        $this->assertCount(1, $links, 'Should only show the public link');

        $linkLabels = array_map(fn(AdminLink $link) => $link->getLabel(), $links);
        $this->assertSame(['menu_admin_public'], $linkLabels, 'Restricted link should be filtered out');
    }
}
