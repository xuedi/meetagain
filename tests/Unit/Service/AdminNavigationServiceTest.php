<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AdminLink;
use App\Entity\AdminSection;
use App\Service\AdminNavigationExtensionInterface;
use App\Service\AdminNavigationService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\Service\AdminNavigationService
 */
final class AdminNavigationServiceTest extends TestCase
{
    private Security $security;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
    }

    public function testGetSidebarSectionsReturnsStaticSectionsOnly(): void
    {
        // Arrange - no extensions
        $service = new AdminNavigationService(
            $this->security,
            [],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should have static sections from YAML
        $this->assertNotEmpty($sections);
        $this->assertContainsOnlyInstancesOf(AdminSection::class, $sections);

        // Check one known section from config/admin_navigation.yaml
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);
        $this->assertContains('System', $sectionNames);
    }

    public function testGetSidebarSectionsMergesPluginExtensions(): void
    {
        // Arrange - create a mock extension
        $mockExtension = new class implements AdminNavigationExtensionInterface {
            public function getPriority(): int
            {
                return 300;
            }

            public function getAdminSections(): array
            {
                return [
                    new AdminSection(
                        section: 'Test Plugin',
                        links: [
                            new AdminLink('Test Link', 'test_route', 'test'),
                        ],
                    ),
                ];
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$mockExtension],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertNotEmpty($sections);
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);

        // Should contain both static sections and plugin section
        $this->assertContains('System', $sectionNames);
        $this->assertContains('Test Plugin', $sectionNames);
    }

    public function testGetSidebarSectionsSortsByPriority(): void
    {
        // Arrange - create two extensions with different priorities
        $lowPriorityExtension = new class implements AdminNavigationExtensionInterface {
            public function getPriority(): int
            {
                return 100;
            }

            public function getAdminSections(): array
            {
                return [
                    new AdminSection(
                        section: 'Low Priority',
                        links: [new AdminLink('Link', 'route', 'active')],
                    ),
                ];
            }
        };

        $highPriorityExtension = new class implements AdminNavigationExtensionInterface {
            public function getPriority(): int
            {
                return 500;
            }

            public function getAdminSections(): array
            {
                return [
                    new AdminSection(
                        section: 'High Priority',
                        links: [new AdminLink('Link', 'route', 'active')],
                    ),
                ];
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$lowPriorityExtension, $highPriorityExtension],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - high priority should come before low priority
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);

        $highPriorityIndex = array_search('High Priority', $sectionNames, true);
        $lowPriorityIndex = array_search('Low Priority', $sectionNames, true);

        $this->assertNotFalse($highPriorityIndex);
        $this->assertNotFalse($lowPriorityIndex);
        $this->assertLessThan($lowPriorityIndex, $highPriorityIndex, 'High priority section should appear first');
    }

    public function testGetSidebarSectionsRespectsRoleRestrictions(): void
    {
        // Arrange
        $service = new AdminNavigationService(
            $this->security,
            [],
            __DIR__ . '/../../..',
        );

        // Deny ROLE_ADMIN (required for System section)
        $this->security->method('isGranted')->willReturn(false);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should not contain admin-only sections
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);
        $this->assertNotContains('System', $sectionNames, 'System section should be hidden without ROLE_ADMIN');
    }

    public function testExtensionCanProvideMultipleSections(): void
    {
        // Arrange - extension that returns multiple sections
        $multiSectionExtension = new class implements AdminNavigationExtensionInterface {
            public function getPriority(): int
            {
                return 300;
            }

            public function getAdminSections(): array
            {
                return [
                    new AdminSection(
                        section: 'Plugin Section 1',
                        links: [new AdminLink('Link 1', 'route1', 'active1')],
                    ),
                    new AdminSection(
                        section: 'Plugin Section 2',
                        links: [new AdminLink('Link 2', 'route2', 'active2')],
                    ),
                ];
            }
        };

        $service = new AdminNavigationService(
            $this->security,
            [$multiSectionExtension],
            __DIR__ . '/../../..',
        );

        $this->security->method('isGranted')->willReturn(true);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $sectionNames = array_map(fn(AdminSection $s) => $s->getSection(), $sections);
        $this->assertContains('Plugin Section 1', $sectionNames);
        $this->assertContains('Plugin Section 2', $sectionNames);
    }
}
