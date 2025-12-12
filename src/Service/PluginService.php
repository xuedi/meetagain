<?php declare(strict_types=1);

namespace App\Service;

use App\ExtendedFilesystem;

readonly class PluginService
{
    public const string CONFIG_DIR = __DIR__ . '/../../config';
    public const string PLUGIN_DIR = __DIR__ . '/../../plugins';

    public function __construct(
        private CommandService $commandService,
        private ExtendedFilesystem $filesystem,
    ) {
    }

    public function getAdminList(): array
    {
        $plugins = [];
        foreach ($this->parsePluginDir() as $pluginPath) {
            $chunks = explode('/', (string) $pluginPath);
            $pluginKey = end($chunks);
            $manifest = $pluginPath . '/manifest.json';
            if (!$this->filesystem->fileExists($manifest)) {
                continue;
            }
            $manifestContent = $this->filesystem->getFileContents($manifest);
            if ($manifestContent === false) {
                continue;
            }
            $pluginData = json_decode($manifestContent, true);
            $plugins[] = [
                'name' => $pluginData['name'],
                'version' => $pluginData['version'],
                'description' => $pluginData['description'],
                'installed' => $this->isInstalled($pluginKey),
                'enabled' => $this->isEnabled($pluginKey),
            ];
        }

        return $plugins;
    }

    public function getActiveList(): array
    {
        $activePlugins = [];
        $config = $this->getPluginConfig();
        foreach ($config as $key => $value) {
            if ($value === true) {
                $activePlugins[] = $key;
            }
        }

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

    private function parsePluginDir(): array
    {
        $pluginDir = $this->filesystem->getRealPath(self::PLUGIN_DIR);
        if ($pluginDir === false) {
            return [];
        }
        return $this->filesystem->glob($pluginDir . '/*', GLOB_ONLYDIR);
    }

    private function getPluginConfig(): array
    {
        $configPath = $this->filesystem->getRealPath(self::CONFIG_DIR);
        if ($configPath === false) {
            return [];
        }
        $configFile = $configPath . '/plugins.php';

        return include $configFile;
    }

    public function setPluginConfig(array $config): void
    {
        $configPath = $this->filesystem->getRealPath(self::CONFIG_DIR);
        if ($configPath === false) {
            return;
        }
        $configFile = $configPath . '/plugins.php';
        $configItems = [];
        foreach ($config as $key => $value) {
            $configItems[] = "'" . $key . "' => " . ($value ? 'true' : 'false');
        }
        $content = '<?php declare(strict_types=1); return [' . implode(',', $configItems) . '];';
        $this->filesystem->putFileContents($configFile, $content);

        $this->commandService->clearCache();
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
