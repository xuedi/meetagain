<?php declare(strict_types=1);

namespace App;

use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// TODO: https://symfony.com/doc/current/configuration/multiple_kernels.html
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    public function getPluginConfigDirs(): iterable
    {
        $plugins = require $this->getProjectDir() . '/config/plugins.php';
        foreach ($plugins as $pluginName => $pluginEnabled) {
            yield $this->getProjectDir() . '/plugins/' . $pluginName . '/config' => $pluginEnabled;
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->doConfigureContainer($container, $this->getProjectDir() . '/config');
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir => $pluginEnabled) {
            $this->doConfigureContainer($container, $pluginConfigDir);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->doConfigureRoutes($routes, $this->getConfigDir());
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir => $pluginEnabled) {
            if ($pluginEnabled) {
                $this->doConfigureRoutes($routes, $pluginConfigDir);
            }
        }
    }

    private function doConfigureContainer(ContainerConfigurator $container, string $configDir): void
    {
        $container->import($configDir . '/{packages}/*.{php,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,yaml}');

        if (is_file($configDir . '/services.yaml')) {
            $container->import($configDir . '/services.yaml');
            $container->import($configDir . '/{services}_' . $this->environment . '.yaml');
        } else {
            $container->import($configDir . '/{services}.php');
        }
    }

    private function doConfigureRoutes(RoutingConfigurator $routes, string $configDir): void
    {
        $routes->import($configDir . '/{routes}/' . $this->environment . '/*.{php,yaml}');
        $routes->import($configDir . '/{routes}/*.{php,yaml}');

        if (is_file($configDir . '/routes.yaml')) {
            $routes->import($configDir . '/routes.yaml');
        } else {
            $routes->import($configDir . '/{routes}.php');
        }

        $reflection = new ReflectionObject($this);
        if (false !== ($fileName = $reflection->getFileName())) {
            $routes->import($fileName, 'attribute');
        }
    }
}
