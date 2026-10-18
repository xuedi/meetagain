<?php declare(strict_types=1);

namespace App;

use App\DependencyInjection\ActivePluginsPass;
use ReflectionClass;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const string PLUGINS_PARAMETER = 'app.plugins';
    private const string EXCLUDE_VARIABLE = 'APP_PLUGINS_EXCLUDE';

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        $bundles = $this->readBundles($this->getConfigDir() . '/bundles.php');
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir) {
            $bundles += $this->readBundles($pluginConfigDir . '/bundles.php');
        }

        foreach ($bundles as $class => $envs) {
            if (!($envs[$this->environment] ?? $envs['all'] ?? false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            // @mago-expect analyzer:unsafe-instantiation
            // @mago-expect analyzer:deprecated-class
            yield new $class();
        }
    }

    /**
     * @return iterable<string, string> plugin key => its config directory
     */
    public function getPluginConfigDirs(): iterable
    {
        $excluded = array_map(trim(...), explode(',', (string) ($_SERVER[self::EXCLUDE_VARIABLE] ?? $_ENV[self::EXCLUDE_VARIABLE] ?? '')));

        $manifests = glob($this->getProjectDir() . '/plugins/*/manifest.json') ?: [];
        sort($manifests);
        foreach ($manifests as $manifest) {
            $pluginDir = dirname($manifest);
            $key = basename($pluginDir);
            if (in_array($key, $excluded, true) || !is_dir($pluginDir . '/config')) {
                continue;
            }

            yield $key => $pluginDir . '/config';
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

    /**
     * @return iterable<string>
     */
    public function getPluginModuleConfigDirs(string $pluginConfigDir): iterable
    {
        foreach (glob(dirname($pluginConfigDir) . '/modules/*/config', GLOB_ONLYDIR) ?: [] as $moduleConfigDir) {
            yield $moduleConfigDir;
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $this->doConfigureContainer($container, $this->getProjectDir() . '/config');
        foreach ($this->getModuleConfigDirs() as $moduleConfigDir) {
            $this->doConfigureContainer($container, $moduleConfigDir);
        }
        foreach ($this->getPluginConfigDirs() as $pluginConfigDir) {
            $this->doConfigureContainer($container, $pluginConfigDir);
            foreach ($this->getPluginModuleConfigDirs($pluginConfigDir) as $moduleConfigDir) {
                $this->doConfigureContainer($container, $moduleConfigDir);
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->doConfigureRoutes($routes, $this->getConfigDir());
        foreach ($this->getModuleConfigDirs() as $moduleConfigDir) {
            $this->doConfigureRoutes($routes, $moduleConfigDir);
        }
        foreach ($this->getPluginConfigDirs() as $pluginKey => $pluginConfigDir) {
            $this->doConfigureRoutes($routes, $pluginConfigDir, $pluginKey);
            foreach ($this->getPluginModuleConfigDirs($pluginConfigDir) as $moduleConfigDir) {
                $this->doConfigureRoutes($routes, $moduleConfigDir, $pluginKey);
            }
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        $plugins = [];
        $container->addResource(new GlobResource($this->getProjectDir() . '/plugins', '/*/manifest.json', false));
        foreach ($this->getPluginConfigDirs() as $pluginKey => $pluginConfigDir) {
            $manifest = dirname($pluginConfigDir) . '/manifest.json';
            $container->addResource(new FileResource($manifest));
            $plugins[$pluginKey] = $this->readManifest($manifest);
        }
        $container->setParameter(self::PLUGINS_PARAMETER, $plugins);
        $container->addCompilerPass(new ActivePluginsPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    /**
     * @return array<class-string<BundleInterface>, array<string, bool>>
     */
    private function readBundles(string $bundlesFile): array
    {
        if (!file_exists($bundlesFile)) {
            return [];
        }

        return require $bundlesFile;
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

    /**
     * @return array{name: string, version: string, description: string, icon: string, group_activatable: bool, system: bool, requires: list<string>}
     */
    private function readManifest(string $manifestFile): array
    {
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        $manifest = is_array($manifest) ? $manifest : [];
        $key = basename(dirname($manifestFile));
        $requires = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];

        return [
            'name' => is_string($manifest['name'] ?? null) ? $manifest['name'] : $key,
            'version' => is_string($manifest['version'] ?? null) ? $manifest['version'] : '0.0.0',
            'description' => is_string($manifest['description'] ?? null) ? $manifest['description'] : '',
            'icon' => is_string($manifest['icon'] ?? null) ? $manifest['icon'] : 'plug',
            'group_activatable' => ($manifest['group_activatable'] ?? true) !== false,
            'system' => ($manifest['system'] ?? false) === true,
            'requires' => array_values(array_map(strval(...), $requires)),
        ];
    }

    private function doConfigureRoutes(RoutingConfigurator $routes, string $configDir, ?string $pluginKey = null): void
    {
        $imports = [
            $routes->import($configDir . '/{routes}/' . $this->environment . '/*.{php,yaml}'),
            $routes->import($configDir . '/{routes}/*.{php,yaml}'),
            $routes->import(is_file($configDir . '/routes.yaml') ? $configDir . '/routes.yaml' : $configDir . '/{routes}.php'),
        ];

        $reflection = new ReflectionObject($this);
        if (false !== ($fileName = $reflection->getFileName())) {
            $imports[] = $routes->import($fileName, 'attribute');
        }

        if ($pluginKey === null) {
            return;
        }
        foreach ($imports as $import) {
            $import->defaults(['_plugin' => $pluginKey]);
        }
    }
}
