<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\AdminModules\AdminModuleInterface;
use App\Routing\AdminModuleRouteLoader;
use App\Service\AdminService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminModuleRouteLoader::class)]
class AdminModuleRouteLoaderTest extends TestCase
{
    public function testLoadRoutesWithoutConflicts(): void
    {
        // Arrange: Create two modules with unique route names
        $module1 = $this->createMock(AdminModuleInterface::class);
        $module1
            ->method('getRoutes')
            ->willReturn([
                [
                    'name' => 'app_admin_test1',
                    'path' => '/admin/test1',
                    'controller' => [\stdClass::class, 'index'],
                ],
            ]);

        $module2 = $this->createMock(AdminModuleInterface::class);
        $module2
            ->method('getRoutes')
            ->willReturn([
                [
                    'name' => 'app_admin_test2',
                    'path' => '/admin/test2',
                    'controller' => [\stdClass::class, 'index'],
                ],
            ]);

        $adminService = $this->createStub(AdminService::class);
        $adminService->method('getAllModules')->willReturn([$module1, $module2]);

        $loader = new AdminModuleRouteLoader($adminService);

        // Act: Load routes
        $routes = $loader->load(null, 'admin_module');

        // Assert: Should have 2 routes without errors
        $this->assertCount(2, $routes);
        $this->assertTrue($routes->get('app_admin_test1') !== null);
        $this->assertTrue($routes->get('app_admin_test2') !== null);
    }

    public function testLoadRoutesDetectsConflict(): void
    {
        // Arrange: Create two modules with duplicate route names
        $module1 = $this->createMock(AdminModuleInterface::class);
        $module1
            ->method('getRoutes')
            ->willReturn([
                [
                    'name' => 'app_admin_duplicate',
                    'path' => '/admin/path1',
                    'controller' => [\stdClass::class, 'index'],
                ],
            ]);

        $module2 = $this->createMock(AdminModuleInterface::class);
        $module2
            ->method('getRoutes')
            ->willReturn([
                [
                    'name' => 'app_admin_duplicate',
                    'path' => '/admin/path2',
                    'controller' => [\stdClass::class, 'other'],
                ],
            ]);

        $adminService = $this->createStub(AdminService::class);
        $adminService->method('getAllModules')->willReturn([$module1, $module2]);

        $loader = new AdminModuleRouteLoader($adminService);

        // Act & Assert: Should throw exception with clear message
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Route name conflict/');
        $this->expectExceptionMessageMatches('/app_admin_duplicate/');
        $this->expectExceptionMessageMatches('/First defined by/');
        $this->expectExceptionMessageMatches('/Duplicate in/');

        $loader->load(null, 'admin_module');
    }

    public function testSupportsAdminModuleType(): void
    {
        // Arrange
        $adminService = $this->createStub(AdminService::class);
        $loader = new AdminModuleRouteLoader($adminService);

        // Act & Assert
        $this->assertTrue($loader->supports(null, 'admin_module'));
        $this->assertFalse($loader->supports(null, 'other_type'));
    }

    public function testLoadThrowsExceptionWhenCalledTwice(): void
    {
        // Arrange
        $adminService = $this->createStub(AdminService::class);
        $adminService->method('getAllModules')->willReturn([]);

        $loader = new AdminModuleRouteLoader($adminService);
        $loader->load(null, 'admin_module'); // First load

        // Act & Assert: Second load should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Do not add the "admin_module" loader twice');

        $loader->load(null, 'admin_module');
    }
}
