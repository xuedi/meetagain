<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\AdminSection;
use App\Service\AdminService;
use PHPUnit\Framework\TestCase;

class AdminServiceTest extends TestCase
{
    public function testGetSidebarSectionsWithNoModules(): void
    {
        // Arrange
        $service = new AdminService([]);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertSame([], $sections);
    }

    public function testGetSidebarSectionsWithSingleAccessibleModule(): void
    {
        // Arrange
        $module = $this->createMockModule(
            key: 'test_module',
            priority: 100,
            sectionName: 'Test Section',
            links: [
                new AdminLink('Test Link', 'test_route', 'test_active'),
            ],
            isAccessible: true,
        );

        $service = new AdminService([$module]);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections);
        $this->assertInstanceOf(AdminSection::class, $sections[0]);
        $this->assertSame('Test Section', $sections[0]->getSection());
        $this->assertCount(1, $sections[0]->getLinks());
        $this->assertSame('Test Link', $sections[0]->getLinks()[0]->getLabel());
    }

    public function testGetSidebarSectionsFiltersInaccessibleModules(): void
    {
        // Arrange
        $accessibleModule = $this->createMockModule(
            key: 'accessible',
            priority: 100,
            sectionName: 'Visible',
            links: [new AdminLink('Link 1', 'route1', 'active1')],
            isAccessible: true,
        );

        $inaccessibleModule = $this->createMockModule(
            key: 'inaccessible',
            priority: 90,
            sectionName: 'Hidden',
            links: [new AdminLink('Link 2', 'route2', 'active2')],
            isAccessible: false,
        );

        $service = new AdminService([$accessibleModule, $inaccessibleModule]);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(1, $sections);
        $this->assertSame('Visible', $sections[0]->getSection());
    }

    public function testGetSidebarSectionsGroupsModulesBySectionName(): void
    {
        // Arrange
        $module1 = $this->createMockModule(
            key: 'module1',
            priority: 100,
            sectionName: 'System',
            links: [new AdminLink('Link 1', 'route1', 'active1')],
            isAccessible: true,
        );

        $module2 = $this->createMockModule(
            key: 'module2',
            priority: 90,
            sectionName: 'System',
            links: [new AdminLink('Link 2', 'route2', 'active2')],
            isAccessible: true,
        );

        $module3 = $this->createMockModule(
            key: 'module3',
            priority: 80,
            sectionName: 'Tables',
            links: [new AdminLink('Link 3', 'route3', 'active3')],
            isAccessible: true,
        );

        $service = new AdminService([$module1, $module2, $module3]);

        // Act
        $sections = $service->getSidebarSections();

        // Assert
        $this->assertCount(2, $sections);
        // System section should have 2 links
        $systemSection = array_values(array_filter($sections, fn($s) => $s->getSection() === 'System'))[0];
        $this->assertCount(2, $systemSection->getLinks());
        // Tables section should have 1 link
        $tablesSection = array_values(array_filter($sections, fn($s) => $s->getSection() === 'Tables'))[0];
        $this->assertCount(1, $tablesSection->getLinks());
    }

    public function testGetSidebarSectionsSortsByPriority(): void
    {
        // Arrange - modules with different priorities
        $lowPriority = $this->createMockModule(
            key: 'low',
            priority: 10,
            sectionName: 'Low Priority',
            links: [new AdminLink('Low', 'low_route', 'low')],
            isAccessible: true,
        );

        $highPriority = $this->createMockModule(
            key: 'high',
            priority: 100,
            sectionName: 'High Priority',
            links: [new AdminLink('High', 'high_route', 'high')],
            isAccessible: true,
        );

        $mediumPriority = $this->createMockModule(
            key: 'medium',
            priority: 50,
            sectionName: 'Medium Priority',
            links: [new AdminLink('Medium', 'medium_route', 'medium')],
            isAccessible: true,
        );

        // Add in random order
        $service = new AdminService([$lowPriority, $highPriority, $mediumPriority]);

        // Act
        $sections = $service->getSidebarSections();

        // Assert - should be sorted by priority (high to low)
        $this->assertCount(3, $sections);
        $this->assertSame('High Priority', $sections[0]->getSection());
        $this->assertSame('Medium Priority', $sections[1]->getSection());
        $this->assertSame('Low Priority', $sections[2]->getSection());
    }

    public function testGetAllModulesReturnsAllModules(): void
    {
        // Arrange
        $module1 = $this->createMockModule('module1', 100, 'Section', [], true);
        $module2 = $this->createMockModule('module2', 90, 'Section', [], true);

        $service = new AdminService([$module1, $module2]);

        // Act
        $modules = iterator_to_array($service->getAllModules());

        // Assert
        $this->assertCount(2, $modules);
    }

    /**
     * @param list<AdminLink> $links
     */
    private function createMockModule(
        string $key,
        int $priority,
        string $sectionName,
        array $links,
        bool $isAccessible,
    ): AdminModuleInterface {
        $module = $this->createMock(AdminModuleInterface::class);

        $module->method('getKey')->willReturn($key);
        $module->method('getPriority')->willReturn($priority);
        $module->method('getSectionName')->willReturn($sectionName);
        $module->method('getLinks')->willReturn($links);
        $module->method('isAccessible')->willReturn($isAccessible);
        $module->method('getRoutes')->willReturn([]);

        return $module;
    }
}
