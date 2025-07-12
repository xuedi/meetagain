<?php declare(strict_types=1);

namespace App\Service;

use App\Plugin as PluginInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class PluginService
{
    public const string CONFIG_DIR = __DIR__ . '/../../config';
    public const string PLUGIN_DIR = __DIR__ . '/../../plugins';

    public function __construct(
        #[AutowireIterator(PluginInterface::class)]
        private iterable $plugins,
        private CommandService $commandService,
    )
    {
    }

    public function getAdminList(): array
    {
        $plugins = [];
        foreach ($this->parsePluginDir() as $pluginPath) {
            $chunks = explode('/', $pluginPath);
            $pluginKey = end($chunks);
            $manifest = $pluginPath . '/manifest.json';
            if (!file_exists($manifest)) {
                continue;
            }
            $pluginData = json_decode(file_get_contents($manifest), true);
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
        $pluginDir = realpath(self::PLUGIN_DIR);
        return glob($pluginDir . '/*', GLOB_ONLYDIR);
    }

    private function getPluginConfig(): array
    {
        $configPath = realpath(self::CONFIG_DIR);
        $configFile = $configPath . '/plugins.php';

        return include $configFile;
    }

    public function setPluginConfig(array $config): void
    {
        $configPath = realpath(self::CONFIG_DIR);
        $configFile = $configPath . '/plugins.php';
        $configItems = [];
        foreach ($config as $key => $value) {
            $configItems[] = "'" . $key . "' => " . ($value ? 'true' : 'false');
        }
        $content = "<?php declare(strict_types=1); return [" . implode(',', $configItems) . "];";
        file_put_contents($configFile, $content);

        $this->commandService->clearCache();
    }

    private function isInstalled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();
        if (!isset($config[$pluginKey])) {
            return false;
        }

        return true;
    }

    private function isEnabled(string $pluginKey): bool
    {
        $config = $this->getPluginConfig();
        if (!isset($config[$pluginKey])) {
            return false;
        }
        if ($config[$pluginKey] !== true) {
            return false;
        }

        return true;
    }
}
