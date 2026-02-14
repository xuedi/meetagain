<?php declare(strict_types=1);

namespace App\Service;

use App\ExtendedFilesystem;
use JsonException;
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
                'name' => $pluginData['name'] ?? $pluginKey,
                'version' => $pluginData['version'] ?? '0.0.0',
                'description' => $pluginData['description'] ?? '',
                'installed' => $this->isInstalled($pluginKey),
                'enabled' => $this->isEnabled($pluginKey),
            ];
        }

        return $plugins;
    }

    public function getActiveList(): array
    {
        $config = $this->getPluginConfig();

        $activePlugins = array_keys(array_filter($config, fn($enabled) => $enabled === true));

        // Core plugins are always active
        $activePlugins[] = 'core_navigation';

        return $activePlugins;
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
