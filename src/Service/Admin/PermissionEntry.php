<?php declare(strict_types=1);

namespace App\Service\Admin;

final readonly class PermissionEntry
{
    public function __construct(
        public string  $routeName,
        public string  $routePath,
        public array   $httpMethods,
        public string  $controllerClass,
        public string  $controllerFqcn,
        public string  $methodName,
        public array   $classPermissions,
        public array   $methodPermissions,
        public ?string $resolvedMinRole,
    ) {}

    public function effectivePermissions(): array
    {
        return $this->methodPermissions !== [] ? $this->methodPermissions : $this->classPermissions;
    }
}
