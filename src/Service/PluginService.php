<?php declare(strict_types=1);

namespace App\Service;

use App\ExtendedFilesystem;

readonly class PluginService
{
    private string $configDir;
    private string $pluginDir;

    public function __construct(
        private CommandService $commandService,
        private ExtendedFilesystem $filesystem,
        string $kernelProjectDir,
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
            } catch (\JsonException) {
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
        return array_keys(array_filter($config, fn($enabled) => $enabled === true));
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

    private function parsePluginDir(): array
    {
        if (!$this->filesystem->exists($this->pluginDir)) {
            return [];
        }
        return $this->filesystem->glob($this->pluginDir . '/*', GLOB_ONLYDIR);
    }

    private function getPluginConfig(): array
    {
        $configFile = $this->configDir . '/plugins.php';
        if (!$this->filesystem->fileExists($configFile)) {
            return [];
        }

        try {
            $config = include $configFile;
            return is_array($config) ? $config : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setPluginConfig(array $config): void
    {
        $configFile = $this->configDir . '/plugins.php';
        $content = '<?php declare(strict_types=1);' . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL;
        if ($this->filesystem->putFileContents($configFile, $content)) {
            $this->commandService->clearCache();
        }
    }

    private function isInstalled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();
        return isset($config[$pluginKey]);
    }

    private function isEnabled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();
        if (!isset($config[$pluginKey])) {
            return false;
        }
        return $config[$pluginKey] === true;
    }
}
