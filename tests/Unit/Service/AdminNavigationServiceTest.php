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
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_system', route: 'app_admin_system', active: 'system'),
                ]);
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
                return new AdminNavigationConfig(section: 'Zebra', links: [new AdminLink(
                    label: 'menu_zebra',
                    route: 'app_zebra',
                )]);
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Apple', links: [new AdminLink(
                    label: 'menu_apple',
                    route: 'app_apple',
                )]);
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Mango', links: [new AdminLink(
                    label: 'menu_mango',
                    route: 'app_mango',
                )]);
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
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_zebra', route: 'app_admin_zebra'),
                ]);
            }
        };

        $controllerA = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_apple', route: 'app_admin_apple'),
                ]);
            }
        };

        $controllerM = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_mango', route: 'app_admin_mango'),
                ]);
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
                return new AdminNavigationConfig(
                    section: 'System',
                    links: [
                        new AdminLink(label: 'menu_admin_system', route: 'app_admin_system'),
                    ],
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
        // Arrange - controller with multiple links (Email and Translation in System section)
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_email', route: 'app_admin_email_templates', active: 'email'),
                    new AdminLink(
                        label: 'menu_admin_translation',
                        route: 'app_admin_translation',
                        active: 'translation',
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
        $systemSection = $sections[0];
        $this->assertSame('System', $systemSection->getSection());

        $links = $systemSection->getLinks();
        $this->assertCount(2, $links, 'Should have two links from the same controller');

        $linkLabels = array_map(fn(AdminLink $link) => $link->getLabel(), $links);
        $this->assertSame(
            ['menu_admin_email', 'menu_admin_translation'],
            $linkLabels,
            'Both links should be present',
        );
    }

    public function testMultiLinkControllerLinksSortedAlphabeticallyWithOtherControllers(): void
    {
        // Arrange - mix of single-link and multi-link controllers in same section
        $singleLinkController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'menu_admin_banana', route: 'app_admin_banana'),
                ]);
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

    public function testModifiesChangesLinkSection(): void
    {
        // Arrange - base controller provides link, modifier changes its section
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'CMS', links: [
                    new AdminLink(label: 'Base CMS', route: 'app_admin_cms', active: 'cms'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'Group',
                    links: [],
                    modifies: [
                        'app_admin_cms' => [
                            'section' => 'Group Section',
                            'label' => 'Modified CMS',
                        ],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$baseController, $modifierController]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - link should appear in modified section with modified label
        $this->assertCount(1, $sections, 'Should have one section');
        $this->assertEquals('Group Section', $sections[0]->getSection(), 'Section should be modified');

        $links = $sections[0]->getLinks();
        $this->assertCount(1, $links, 'Should have one link');
        $this->assertEquals('Modified CMS', $links[0]->getLabel(), 'Label should be modified');
        $this->assertEquals('app_admin_cms', $links[0]->getRoute(), 'Route should remain unchanged');
    }

    public function testModifiesOnlyAffectsSpecifiedRoute(): void
    {
        // Arrange - multiple controllers, only one route is modified
        $cmsController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'CMS', links: [new AdminLink(
                    label: 'Base CMS',
                    route: 'app_admin_cms',
                )]);
            }
        };

        $systemController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [new AdminLink(
                    label: 'System',
                    route: 'app_admin_system',
                )]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'Group',
                    links: [],
                    modifies: [
                        'app_admin_cms' => ['section' => 'Group'],
                    ],
                );
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$cmsController, $systemController, $modifierController],
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(2, $sections, 'Should have two sections (System and Group)');

        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);
        $this->assertContains('System', $sectionNames, 'System section should be visible');
        $this->assertContains('Group', $sectionNames, 'Group section should be visible');
        $this->assertNotContains('CMS', $sectionNames, 'CMS section should not exist (link moved to Group)');
    }

    public function testMultipleModificationsOverwriteEachOther(): void
    {
        // Arrange - multiple controllers modifying the same route (last one wins)
        $cmsController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'CMS', links: [new AdminLink(
                    label: 'CMS',
                    route: 'app_admin_cms',
                )]);
            }
        };

        $modifier1 = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'Plugin1',
                    links: [],
                    modifies: [
                        'app_admin_cms' => ['section' => 'Modified Section 1', 'label' => 'Label 1'],
                    ],
                );
            }
        };

        $modifier2 = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'Plugin2',
                    links: [],
                    modifies: [
                        'app_admin_cms' => ['section' => 'Modified Section 2', 'label' => 'Label 2'],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$cmsController, $modifier1, $modifier2]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - last modification wins
        $this->assertCount(1, $sections, 'Should have one section');
        $this->assertEquals('Modified Section 2', $sections[0]->getSection(), 'Last modifier should win');

        $links = $sections[0]->getLinks();
        $this->assertEquals('Label 2', $links[0]->getLabel(), 'Last modifier should set label');
    }

    public function testModifiesCanChangeMultipleProperties(): void
    {
        // Arrange - modifier changes section, label, and active
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Original', links: [
                    new AdminLink(label: 'Original Label', route: 'app_admin_test', active: 'original'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'Modified',
                    links: [],
                    modifies: [
                        'app_admin_test' => [
                            'section' => 'New Section',
                            'label' => 'New Label',
                            'active' => 'new_active',
                        ],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, [$baseController, $modifierController]);

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections);
        $this->assertEquals('New Section', $sections[0]->getSection());

        $link = $sections[0]->getLinks()[0];
        $this->assertEquals('New Label', $link->getLabel());
        $this->assertEquals('new_active', $link->getActive());
        $this->assertEquals('app_admin_test', $link->getRoute());
    }
}
