<?php declare(strict_types=1);

namespace App\Service\Admin;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

readonly class PermissionInspectorService
{
    private const ROLE_ORDER = ['ROLE_USER', 'ROLE_ORGANIZER', 'ROLE_ADMIN'];
    public const ANONYMOUS = 'Anonymous';

    public function __construct(
        private RouterInterface $router,
        private RoleHierarchyInterface $roleHierarchy,
    ) {}

    /** @return PermissionEntry[] */
    public function getEntries(): array
    {
        $entries = [];

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $controller = $route->getDefault('_controller');

            if (!is_string($controller) || !str_contains($controller, '::')) {
                continue;
            }

            [$fqcn, $methodName] = explode('::', $controller, 2);

            if (!class_exists($fqcn)) {
                continue;
            }

            $classRef  = new ReflectionClass($fqcn);
            $methodRef = new ReflectionMethod($fqcn, $methodName);

            $classRoleIds  = $this->collectRoleIds($classRef->getAttributes(IsGranted::class));
            $methodRoleIds = $this->collectRoleIds($methodRef->getAttributes(IsGranted::class));

            $effective       = $methodRoleIds !== [] ? $methodRoleIds : $classRoleIds;
            $resolvedMinRole = $effective[0] ?? null;

            $entries[] = new PermissionEntry(
                routeName:         $routeName,
                routePath:         $route->getPath(),
                httpMethods:       $route->getMethods(),
                controllerClass:   $classRef->getShortName(),
                controllerFqcn:    $fqcn,
                methodName:        $methodName,
                classPermissions:  $classRoleIds,
                methodPermissions: $methodRoleIds,
                resolvedMinRole:   $resolvedMinRole,
            );
        }

        return $entries;
    }

    /** @return array<string, PermissionEntry[]> */
    public function getEntriesGroupedByRole(): array
    {
        $groups = [];

        foreach ($this->getEntries() as $entry) {
            $key           = $entry->resolvedMinRole ?? self::ANONYMOUS;
            $groups[$key][] = $entry;
        }

        return $groups;
    }

    /** @return string[] role identifiers sorted ascending by privilege, Anonymous last */
    public function getRoleDisplayOrder(): array
    {
        $known = self::ROLE_ORDER;
        $groups = $this->getEntriesGroupedByRole();

        foreach (array_keys($groups) as $role) {
            if ($role !== self::ANONYMOUS && !in_array($role, $known, true)) {
                $known[] = $role;
            }
        }

        $known[] = self::ANONYMOUS;

        return $known;
    }

    /** @param \ReflectionAttribute[] $attributes */
    private function collectRoleIds(array $attributes): array
    {
        return array_map(
            static fn(\ReflectionAttribute $attr) => $attr->newInstance()->attribute,
            $attributes,
        );
    }
}
