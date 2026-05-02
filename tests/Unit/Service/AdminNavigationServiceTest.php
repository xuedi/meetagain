<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Controller\Admin\AdminNavigationConfig;
use App\Controller\Admin\AdminNavigationInterface;
use App\Entity\AdminLink;
use App\Entity\AdminSection;
use App\Service\Admin\AdminNavigationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @covers \App\Service\AdminNavigationService
 */
#[AllowMockObjectsWithoutExpectations]
final class AdminNavigationServiceTest extends TestCase
{
    private Security $security;
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->router = $this->createStub(RouterInterface::class);
        $this->router->method('generate')->willReturn('/some/path');
    }

    private function allowAll(): void
    {
        $this->security->method('isGranted')->willReturn(true);
    }

    private function denyAll(): void
    {
        $this->security->method('isGranted')->willReturn(false);
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

        $service = new AdminNavigationService($this->security, $this->router, [$mockController], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertNotEmpty($sections);
        static::assertContainsOnlyInstancesOf(AdminSection::class, $sections);

        $sectionNames = array_map(static fn(AdminSection $s) => $s->getSection(), $sections);
        static::assertContains('System', $sectionNames);
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

        $service = new AdminNavigationService(
            $this->security,
            $this->router,
            [$controllerZ, $controllerA, $controllerM],
            [],
        );

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - sections should be in alphabetical order
        $sectionNames = array_map(static fn(AdminSection $s) => $s->getSection(), $sections);

        static::assertSame(['Apple', 'Mango', 'Zebra'], $sectionNames, 'Sections should be sorted alphabetically');
    }

    public function testGetSidebarSectionsSortsByPriorityThenAlphabetically(): void
    {
        // Arrange - "System" section at priority 100 should appear after priority-0 sections,
        // even if alphabetically it would come before (e.g., "Zebra" > "System" but priority wins)
        $pluginController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Zebra Group', links: [new AdminLink(
                    label: 'menu_zebra',
                    route: 'app_zebra',
                )]);
            }
        };

        $systemController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    links: [new AdminLink(label: 'menu_system', route: 'app_system')],
                    sectionPriority: 100,
                );
            }
        };

        $anotherPluginController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Apple Group', links: [new AdminLink(
                    label: 'menu_apple',
                    route: 'app_apple',
                )]);
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            $this->router,
            [$systemController, $pluginController, $anotherPluginController],
            [],
        );

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - priority-0 sections appear first (alphabetically), then priority-100
        $sectionNames = array_map(static fn(AdminSection $s) => $s->getSection(), $sections);
        static::assertSame(
            ['Apple Group', 'Zebra Group', 'System'],
            $sectionNames,
            'Priority-0 sections appear before priority-100, alphabetically within same priority',
        );
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

        $service = new AdminNavigationService(
            $this->security,
            $this->router,
            [$controllerZ, $controllerA, $controllerM],
            [],
        );

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];
        static::assertSame('System', $systemSection->getSection());

        $linkLabels = array_map(static fn(AdminLink $link) => $link->getLabel(), $systemSection->getLinks());
        static::assertSame(
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

        $service = new AdminNavigationService($this->security, $this->router, [$mockController], []);

        // Deny ROLE_ADMIN
        $this->denyAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should not contain admin-only sections
        static::assertEmpty($sections, 'System section should be hidden without Admin role');
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

        $service = new AdminNavigationService($this->security, $this->router, [$mockController], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should have no sections
        static::assertEmpty($sections, 'Controllers returning null should not appear in navigation');
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

        $service = new AdminNavigationService($this->security, $this->router, [$mockController], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];
        static::assertSame('System', $systemSection->getSection());

        $links = $systemSection->getLinks();
        static::assertCount(2, $links, 'Should have two links from the same controller');

        $linkLabels = array_map(static fn(AdminLink $link) => $link->getLabel(), $links);
        static::assertSame(['menu_admin_email', 'menu_admin_translation'], $linkLabels, 'Both links should be present');
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

        $service = new AdminNavigationService(
            $this->security,
            $this->router,
            [$singleLinkController, $multiLinkController],
            [],
        );

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];

        $linkLabels = array_map(static fn(AdminLink $link) => $link->getLabel(), $systemSection->getLinks());
        static::assertSame(
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
                        role: 'ROLE_ADMIN',
                    ),
                ]);
            }
        };

        $service = new AdminNavigationService($this->security, $this->router, [$mockController], []);

        // Only deny ROLE_ADMIN
        $this->security
            ->method('isGranted')
            ->willReturnCallback(static fn(string $role) => $role !== 'ROLE_ADMIN');

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections, 'Should have one section');
        $systemSection = $sections[0];

        $links = $systemSection->getLinks();
        static::assertCount(1, $links, 'Should only show the public link');

        $linkLabels = array_map(static fn(AdminLink $link) => $link->getLabel(), $links);
        static::assertSame(['menu_admin_public'], $linkLabels, 'Restricted link should be filtered out');
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

        $service = new AdminNavigationService($this->security, $this->router, [$baseController, $modifierController], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - link should appear in modified section with modified label
        static::assertCount(1, $sections, 'Should have one section');
        static::assertSame('Group Section', $sections[0]->getSection(), 'Section should be modified');

        $links = $sections[0]->getLinks();
        static::assertCount(1, $links, 'Should have one link');
        static::assertSame('Modified CMS', $links[0]->getLabel(), 'Label should be modified');
        static::assertSame('app_admin_cms', $links[0]->getRoute(), 'Route should remain unchanged');
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
            $this->router,
            [$cmsController, $systemController, $modifierController],
            [],
        );

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(2, $sections, 'Should have two sections (System and Group)');

        $sectionNames = array_map(static fn(AdminSection $s) => $s->getSection(), $sections);
        static::assertContains('System', $sectionNames, 'System section should be visible');
        static::assertContains('Group', $sectionNames, 'Group section should be visible');
        static::assertNotContains('CMS', $sectionNames, 'CMS section should not exist (link moved to Group)');
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

        $service = new AdminNavigationService($this->security, $this->router, [$cmsController, $modifier1, $modifier2], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert - last modification wins
        static::assertCount(1, $sections, 'Should have one section');
        static::assertSame('Modified Section 2', $sections[0]->getSection(), 'Last modifier should win');

        $links = $sections[0]->getLinks();
        static::assertSame('Label 2', $links[0]->getLabel(), 'Last modifier should set label');
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

        $service = new AdminNavigationService($this->security, $this->router, [$baseController, $modifierController], []);

        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections);
        static::assertSame('New Section', $sections[0]->getSection());

        $link = $sections[0]->getLinks()[0];
        static::assertSame('New Label', $link->getLabel());
        static::assertSame('new_active', $link->getActive());
        static::assertSame('app_admin_test', $link->getRoute());
    }

    public function testModifiesRouteSwapsLink(): void
    {
        // Arrange - base controller provides core route, modifier swaps it to plugin route
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'Members', route: 'app_admin_member', active: 'member'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    links: [],
                    modifies: [
                        'app_admin_member' => ['route' => 'app_alt_admin_member'],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, $this->router, [$baseController, $modifierController], []);
        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections);
        $links = $sections[0]->getLinks();
        static::assertCount(1, $links);
        static::assertSame('app_alt_admin_member', $links[0]->getRoute(), 'Route should be swapped');
        static::assertSame('Members', $links[0]->getLabel(), 'Label should be unchanged');
    }

    public function testModifiesHiddenRemovesLink(): void
    {
        // Arrange - base controller provides link, modifier hides it
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'Hidden', route: 'app_admin_hidden'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    links: [],
                    modifies: [
                        'app_admin_hidden' => ['hidden' => true],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, $this->router, [$baseController, $modifierController], []);
        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertEmpty($sections, 'Section with only hidden links should not appear');
    }

    public function testModifiesRouteToUnknownRouteSilentlyDropsLink(): void
    {
        // Arrange - swap to a route that does not exist; link is silently dropped
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'Members', route: 'app_admin_member'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'System',
                    links: [],
                    modifies: [
                        'app_admin_member' => ['route' => 'app_does_not_exist'],
                    ],
                );
            }
        };

        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static function (string $name): string {
                if ($name === 'app_does_not_exist') {
                    throw new RouteNotFoundException();
                }

                return '/some/path';
            });

        $service = new AdminNavigationService($this->security, $router, [$baseController, $modifierController], []);
        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertEmpty($sections, 'Link with unknown swapped route should be dropped');
    }

    public function testModifiesCombinedSectionLabelRoute(): void
    {
        // Arrange - modifier swaps route, section, and label in one go
        $baseController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'Old', links: [
                    new AdminLink(label: 'Old Label', route: 'app_old_route'),
                ]);
            }
        };

        $modifierController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(
                    section: 'New',
                    links: [],
                    modifies: [
                        'app_old_route' => [
                            'section' => 'New',
                            'label' => 'New Label',
                            'route' => 'app_new_route',
                        ],
                    ],
                );
            }
        };

        $service = new AdminNavigationService($this->security, $this->router, [$baseController, $modifierController], []);
        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections);
        static::assertSame('New', $sections[0]->getSection());
        $link = $sections[0]->getLinks()[0];
        static::assertSame('New Label', $link->getLabel());
        static::assertSame('app_new_route', $link->getRoute());
    }

    public function testLinksWithNonExistentRoutesAreFiltered(): void
    {
        // Arrange
        $mockController = new class implements AdminNavigationInterface {
            public function getAdminNavigation(): ?AdminNavigationConfig
            {
                return new AdminNavigationConfig(section: 'System', links: [
                    new AdminLink(label: 'Valid Link', route: 'app_existing_route'),
                    new AdminLink(label: 'Missing Link', route: 'app_missing_route'),
                ]);
            }
        };

        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static function (string $name): string {
                if ($name === 'app_missing_route') {
                    throw new RouteNotFoundException();
                }

                return '/some/path';
            });

        $service = new AdminNavigationService($this->security, $router, [$mockController], []);
        $this->allowAll();

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        static::assertCount(1, $sections);
        $links = $sections[0]->getLinks();
        static::assertCount(1, $links, 'Link with non-existent route should be filtered out');
        static::assertSame('Valid Link', $links[0]->getLabel());
    }
}
