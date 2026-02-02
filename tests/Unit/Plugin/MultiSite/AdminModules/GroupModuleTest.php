<?php declare(strict_types=1);

namespace Tests\Unit\Plugin\MultiSite\AdminModules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\MultiSite\AdminModules\GroupModule;
use Symfony\Bundle\SecurityBundle\Security;

#[CoversClass(GroupModule::class)]
class GroupModuleTest extends TestCase
{
    private GroupModule $module;

    protected function setUp(): void
    {
        // Arrange: Mock Security service
        $security = $this->createStub(Security::class);
        $this->module = new GroupModule($security);
    }

    public function testGetLinksReturnsThreeLinks(): void
    {
        // Act: Get links from module
        $links = $this->module->getLinks();

        // Assert: Should have exactly 3 links
        $this->assertCount(3, $links);
        $this->assertSame('All Groups', $links[0]->getLabel());
        $this->assertSame('app_admin_groups', $links[0]->getRoute());
        $this->assertSame('New Group', $links[1]->getLabel());
        $this->assertSame('app_admin_group_new', $links[1]->getRoute());
        $this->assertSame('All Members', $links[2]->getLabel());
        $this->assertSame('app_admin_multisite_members', $links[2]->getRoute());
    }

    public function testGetRoutesReturnsFiveRoutes(): void
    {
        // Act: Get routes from module
        $routes = $this->module->getRoutes();

        // Assert: Should have exactly 5 routes
        $this->assertCount(5, $routes);

        // Verify route names are unique
        $routeNames = array_column($routes, 'name');
        $this->assertCount(5, array_unique($routeNames), 'Route names must be unique');

        // Verify members route is included
        $memberRoute = array_filter($routes, fn(array $r) => $r['name'] === 'app_admin_multisite_members');
        $this->assertCount(1, $memberRoute);
        $this->assertSame('/admin/members', $memberRoute[array_key_first($memberRoute)]['path']);
    }

    public function testGetKeyReturnsGroups(): void
    {
        // Act & Assert
        $this->assertSame('groups', $this->module->getKey());
    }

    public function testGetSectionNameReturnsMultiSite(): void
    {
        // Act & Assert
        $this->assertSame('MultiSite', $this->module->getSectionName());
    }

    public function testGetPriorityReturns300(): void
    {
        // Act & Assert
        $this->assertSame(300, $this->module->getPriority());
    }
}
