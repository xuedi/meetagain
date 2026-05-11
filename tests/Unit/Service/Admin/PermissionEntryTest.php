<?php declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Service\Admin\PermissionEntry;
use PHPUnit\Framework\TestCase;

class PermissionEntryTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        // Arrange / Act
        $entry = new PermissionEntry(
            routeName: 'app_test',
            routePath: '/test',
            httpMethods: ['GET', 'POST'],
            controllerClass: 'TestController',
            controllerFqcn: 'App\\Controller\\TestController',
            methodName: 'index',
            classPermissions: ['class.perm'],
            methodPermissions: ['method.perm'],
            resolvedMinRole: 'ROLE_USER',
        );

        // Assert
        static::assertSame('app_test', $entry->routeName);
        static::assertSame('/test', $entry->routePath);
        static::assertSame(['GET', 'POST'], $entry->httpMethods);
        static::assertSame('TestController', $entry->controllerClass);
        static::assertSame('App\\Controller\\TestController', $entry->controllerFqcn);
        static::assertSame('index', $entry->methodName);
        static::assertSame(['class.perm'], $entry->classPermissions);
        static::assertSame(['method.perm'], $entry->methodPermissions);
        static::assertSame('ROLE_USER', $entry->resolvedMinRole);
    }

    public function testEffectivePermissionsPrefersMethodOverClass(): void
    {
        // Arrange
        $entry = $this->makeEntry(classPermissions: ['class.x'], methodPermissions: ['method.x']);

        // Act / Assert
        static::assertSame(['method.x'], $entry->effectivePermissions());
    }

    public function testEffectivePermissionsFallsBackToClassWhenMethodEmpty(): void
    {
        // Arrange
        $entry = $this->makeEntry(classPermissions: ['class.x'], methodPermissions: []);

        // Act / Assert
        static::assertSame(['class.x'], $entry->effectivePermissions());
    }

    public function testEffectivePermissionsReturnsEmptyWhenBothEmpty(): void
    {
        // Arrange
        $entry = $this->makeEntry(classPermissions: [], methodPermissions: []);

        // Act / Assert
        static::assertSame([], $entry->effectivePermissions());
    }

    /**
     * @param list<string> $classPermissions
     * @param list<string> $methodPermissions
     */
    private function makeEntry(array $classPermissions = [], array $methodPermissions = []): PermissionEntry
    {
        return new PermissionEntry(
            routeName: 'r',
            routePath: '/',
            httpMethods: [],
            controllerClass: 'C',
            controllerFqcn: 'C',
            methodName: 'm',
            classPermissions: $classPermissions,
            methodPermissions: $methodPermissions,
            resolvedMinRole: null,
        );
    }
}
