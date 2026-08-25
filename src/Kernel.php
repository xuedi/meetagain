<?php declare(strict_types=1);

namespace App;

use ReflectionClass;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield from $this->doRegisterBundles($this->getConfigDir() . '/bundles.php');
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir => $pluginEnabled) {
            $bundlesFile = $pluginConfigDir . '/bundles.php';
            if (!file_exists($bundlesFile)) {
                continue;
            }
            yield from $this->doRegisterBundles($bundlesFile);
        }
    }

    /**
     * @return iterable<BundleInterface>
     */
    private function doRegisterBundles(string $bundlesFile): iterable
    {
        if (!file_exists($bundlesFile)) {
            return;
        }
        /** @var array<class-string<BundleInterface>, array<string, bool>> $contents */
        $contents = require $bundlesFile;
        foreach ($contents as $class => $envs) {
            if (!($envs[$this->environment] ?? $envs['all'] ?? false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            // @mago-expect analyzer:unsafe-instantiation
            yield new $class();
        }
    }

    public function getPluginConfigDirs(): iterable
    {
        $envPluginsFile = $this->getProjectDir() . '/config/plugins_' . $this->environment . '.php';
        $plugins = [];
        $pluginsFile = $this->getProjectDir() . '/config/plugins.php';
        if (file_exists($pluginsFile)) {
            $plugins = require $pluginsFile;
        }
        if (file_exists($envPluginsFile)) {
            $plugins = require $envPluginsFile;
        }

        foreach ($plugins as $pluginName => $pluginEnabled) {
            yield $this->getProjectDir() . '/plugins/' . $pluginName . '/config' => $pluginEnabled;
        }
    }

    /**
     * @return iterable<string>
     */
    public function getModuleConfigDirs(): iterable
    {
        foreach (glob($this->getProjectDir() . '/modules/*/config', GLOB_ONLYDIR) ?: [] as $moduleConfigDir) {
            yield $moduleConfigDir;
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->doConfigureContainer($container, $this->getProjectDir() . '/config');
        foreach ($this->getModuleConfigDirs() as $moduleConfigDir) {
            $this->doConfigureContainer($container, $moduleConfigDir);
        }
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir => $pluginEnabled) {
            $this->doConfigureContainer($container, $pluginConfigDir);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->doConfigureRoutes($routes, $this->getConfigDir());
        foreach ($this->getModuleConfigDirs() as $moduleConfigDir) {
            $this->doConfigureRoutes($routes, $moduleConfigDir);
        }
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir => $pluginEnabled) {
            if (!$pluginEnabled) {
                continue;
            }

            $this->doConfigureRoutes($routes, $pluginConfigDir);
        }
    }

    private function doConfigureContainer(ContainerConfigurator $container, string $configDir): void
    {
        $container->import($configDir . '/{packages}/*.{php,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,yaml}');

        if (is_file($configDir . '/services.yaml')) {
            $container->import($configDir . '/services.yaml');
            $container->import($configDir . '/{services}_' . $this->environment . '.yaml');
            return;
        }
        $container->import($configDir . '/{services}.php');
    }

    private function doConfigureRoutes(RoutingConfigurator $routes, string $configDir): void
    {
        $routes->import($configDir . '/{routes}/' . $this->environment . '/*.{php,yaml}');
        $routes->import($configDir . '/{routes}/*.{php,yaml}');

        $routes->import(is_file($configDir . '/routes.yaml') ? $configDir . '/routes.yaml' : $configDir . '/{routes}.php');

        $reflection = new ReflectionObject($this);
        if (false !== ($fileName = $reflection->getFileName())) {
            $routes->import($fileName, 'attribute');
        }
    }
}
