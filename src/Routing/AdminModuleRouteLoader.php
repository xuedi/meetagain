<?php declare(strict_types=1);

namespace App\Routing;

use App\Service\AdminService;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamically loads routes from admin modules.
 * Routes are registered at container compile time (cached).
 */
class AdminModuleRouteLoader extends Loader
{
    private bool $isLoaded = false;

    public function __construct(
        private readonly AdminService $adminService,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "admin_module" loader twice');
        }

        $routes = new RouteCollection();
        $routeNames = []; // Track route names for conflict detection

        foreach ($this->adminService->getAllModules() as $module) {
            foreach ($module->getRoutes() as $routeDefinition) {
                $routeName = $routeDefinition['name'];

                // Conflict detection
                if (isset($routeNames[$routeName])) {
                    throw new \RuntimeException(sprintf(
                        "Route name conflict: '%s'\n"
                        . "  First defined by: %s\n"
                        . "  Duplicate in: %s\n\n"
                        . 'Each AdminModuleInterface must define unique route names.',
                        $routeName,
                        $routeNames[$routeName],
                        get_class($module),
                    ));
                }
                $routeNames[$routeName] = get_class($module);

                $defaults = array_merge($routeDefinition['defaults'] ?? [], [
                    '_controller' => $routeDefinition['controller'],
                ]);

                $route = new Route(
                    path: $routeDefinition['path'],
                    defaults: $defaults,
                    methods: $routeDefinition['methods'] ?? ['GET'],
                );

                $routes->add($routeName, $route);
            }
        }

        $this->isLoaded = true;
        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'admin_module';
    }
}
