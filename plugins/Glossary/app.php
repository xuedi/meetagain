<?php

namespace Plugin\Glossary;

use App\Controller\IndexController;
use Symfony\Component\HttpFoundation\Request;

class app
{
    private array $routes = [];

    public function __construct()
    {
        //
    }

    public function addController(string $class): void
    {
        $controller = new $class();
        foreach ($controller->getRoutes() as $route => $method) {
            $this->routes[] = [
                'route' => trim($route, '/'),
                'class' => $class,
                'method' => $method,
            ];
        }
    }

    public function handleRoute(Request $request)
    {
        foreach ($this->routes as $route) {
            if ($this->hasMatchingPrefix($route['route'], $request->getPathInfo())) {
                $controller = new $route['class']();
                return $controller->{$route['method']}($request);
            }
        }
        return null;
    }

    private function hasMatchingPrefix(string $route, string $pathInfo): bool
    {
        $pathInfo = strtolower(trim(substr($pathInfo, 3), '/'));
        $route = strtolower(trim($route, '/'));

        if($pathInfo === $route) {
            return true;
        }

        return false;
    }
}