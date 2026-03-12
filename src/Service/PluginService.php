<?php declare(strict_types=1);

namespace App\Service;

use App\ExtendedFilesystem;
use App\Filter\Plugin\PluginListFilterInterface;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

readonly class PluginService
{
    private string $configDir;
    private string $pluginDir;

    public function __construct(
        private CommandService $commandService,
        private ExtendedFilesystem $filesystem,
        string $kernelProjectDir,
        private string $environment,
        #[AutowireIterator(PluginListFilterInterface::class)]
        private iterable $pluginListFilters = [],
    ) {
        $this->configDir = $kernelProjectDir . '/config';
        $this->pluginDir = $kernelProjectDir . '/plugins';
    }

    public function getAdminList(): array
    {
        $plugins = [];
        foreach ($this->parsePluginDir() as $pluginPath) {
            $manifest = $pluginPath . '/manifest.json';
            if (!$this->filesystem->fileExists($manifest)) {
                continue;
            }
            $manifestContent = $this->filesystem->getFileContents($manifest);
            if ($manifestContent === false) {
                continue;
            }
            try {
                $pluginData = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $pluginKey = basename((string) $pluginPath);
            $plugins[] = [
                'key'         => $pluginKey,
                'name'        => $pluginData['name'] ?? $pluginKey,
                'version'     => $pluginData['version'] ?? '0.0.0',
                'description' => $pluginData['description'] ?? '',
                'installed'   => $this->isInstalled($pluginKey),
                'enabled'     => $this->isEnabled($pluginKey),
            ];
        }

        return $plugins;
    }

    /**
     * Returns all globally active plugin keys from config/plugins.php.
     * Does NOT apply any context filters — always reflects the platform admin's settings.
     *
     * @return array<string>
     */
    public function getGloballyActiveList(): array
    {
        $config = $this->getPluginConfig();

        $activePlugins = array_keys(array_filter($config, fn($enabled) => $enabled === true));

        // Core plugins are always active
        $activePlugins[] = 'core_navigation';

        return $activePlugins;
    }

    /**
     * Returns active plugin keys for the current request context.
     * Applies all registered PluginListFilterInterface implementations (AND logic).
     * Filters only operate on group-activatable plugins; core and infrastructure plugins
     * (e.g. core_navigation, multisite) are always included regardless of group context.
     *
     * @return array<string>
     */
    public function getActiveList(): array
    {
        $allActive = $this->getGloballyActiveList();

        $activatableKeys = array_column($this->getActivatableByGroupList(), 'key');
        $alwaysOn = array_values(array_diff($allActive, $activatableKeys));
        $groupManaged = array_values(array_intersect($allActive, $activatableKeys));

        foreach ($this->pluginListFilters as $filter) {
            $filtered = $filter->filterActivePlugins($groupManaged);
            if ($filtered !== null) {
                $groupManaged = array_values(array_intersect($groupManaged, $filtered));
            }
        }

        return array_merge($alwaysOn, $groupManaged);
    }

    /**
     * Returns globally active plugins that group founders can activate for their group.
     * Excludes plugins where manifest.json has "group_activatable": false.
     *
     * @return array<array{key: string, name: string, description: string}>
     */
    public function getActivatableByGroupList(): array
    {
        $globallyActive = $this->getGloballyActiveList();
        $result = [];

        foreach ($this->parsePluginDir() as $pluginPath) {
            $pluginKey = basename((string) $pluginPath);

            if (!in_array($pluginKey, $globallyActive, true)) {
                continue;
            }

            $manifest = $pluginPath . '/manifest.json';
            if (!$this->filesystem->fileExists($manifest)) {
                continue;
            }

            $manifestContent = $this->filesystem->getFileContents($manifest);
            if ($manifestContent === false) {
                continue;
            }

            try {
                $pluginData = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            // Skip plugins that have opted out of group activation
            if (isset($pluginData['group_activatable']) && $pluginData['group_activatable'] === false) {
                continue;
            }

            $result[] = [
                'key' => $pluginKey,
                'name' => $pluginData['name'] ?? $pluginKey,
                'description' => $pluginData['description'] ?? '',
            ];
        }

        return $result;
    }

    public function install(string $pluginKey): void
    {
        if ($this->isInstalled($pluginKey)) {
            return;
        }
        $config = $this->getPluginConfig();
        $config[$pluginKey] = false;
        $this->setPluginConfig($config);
    }

    public function uninstall(string $pluginKey): void
    {
        if (!$this->isInstalled($pluginKey)) {
            return;
        }
        $config = $this->getPluginConfig();
        unset($config[$pluginKey]);
        $this->setPluginConfig($config);
    }

    public function enable(string $pluginKey): void
    {
        if (!$this->isInstalled($pluginKey) || $this->isEnabled($pluginKey)) {
            return;
        }
        $config = $this->getPluginConfig();
        $config[$pluginKey] = true;
        $this->setPluginConfig($config);
    }

    public function disable(string $pluginKey): void
    {
        if (!$this->isInstalled($pluginKey) || !$this->isEnabled($pluginKey)) {
            return;
        }
        $config = $this->getPluginConfig();
        $config[$pluginKey] = false;
        $this->setPluginConfig($config);
    }

    public function isInstalled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();

        return isset($config[$pluginKey]);
    }

    public function isEnabled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();
        if (!isset($config[$pluginKey])) {
            return false;
        }

        return $config[$pluginKey] === true;
    }

    private function parsePluginDir(): array
    {
        if (!$this->filesystem->exists($this->pluginDir)) {
            return [];
        }

        return $this->filesystem->glob($this->pluginDir . '/*', GLOB_ONLYDIR);
    }

    private function getPluginConfig(): array
    {
        // Check for environment-specific config first (e.g., plugins_test.php)
        $envConfigFile = $this->configDir . '/plugins_' . $this->environment . '.php';
        if ($this->filesystem->fileExists($envConfigFile)) {
            try {
                $config = include $envConfigFile;

                return is_array($config) ? $config : [];
            } catch (Throwable) {
                // Environment config invalid/corrupt - fall through to default plugins.php
            }
        }

        // Fallback to default plugins.php
        $configFile = $this->configDir . '/plugins.php';
        if (!$this->filesystem->fileExists($configFile)) {
            return [];
        }

        try {
            $config = include $configFile;

            return is_array($config) ? $config : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function setPluginConfig(array $config): void
    {
        $configFile = $this->configDir . '/plugins.php';
        $content = '<?php declare(strict_types=1);' . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL;
        if ($this->filesystem->putFileContents($configFile, $content)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($configFile, true);
            }
            $this->commandService->clearCache();
        }
    }
}
