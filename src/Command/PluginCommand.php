<?php declare(strict_types=1);

namespace App\Command;

use App\Service\PluginService;
use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugin', description: 'List, enable, or disable plugins')]
class PluginCommand extends Command
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Mode: plugin-key (enable), all, none, or no')
            ->addOption('disable', 'd', InputOption::VALUE_NONE, 'Disable mode (applies to plugin-key)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available plugins (default if no mode given)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getOption('mode');
        $disable = $input->getOption('disable');

        // List plugins (default)
        if (!$mode && !$disable) {
            return $this->listPlugins($output);
        }

        // Enable all plugins
        if ($mode === 'all') {
            return $this->enableAllPlugins($output);
        }

        // Disable all plugins (support both 'none' and 'no')
        if ($mode === 'none' || $mode === 'no') {
            return $this->disableAllPlugins($output);
        }

        // Plugin key provided
        if ($mode) {
            if ($disable) {
                return $this->disablePlugins($output, $mode);
            }

            return $this->enablePlugins($output, $mode);
        }

        $output->writeln('Invalid mode or option combination.');
        return Command::FAILURE;
    }

    private function listPlugins(OutputInterface $output): int
    {
        $plugins = $this->getPluginsWithKeys();

        if (empty($plugins)) {
            $output->writeln('No plugins found in plugins/ directory.');

            return Command::SUCCESS;
        }

        $output->writeln('Available Plugins:');
        $output->writeln('');

        foreach ($plugins as $plugin) {
            $status = $plugin['enabled']
                ? 'Enabled'
                : ($plugin['installed'] ? 'Installed' : 'Available');

            $output->writeln(sprintf(
                '  %s - %s (%s) [%s]',
                $plugin['key'],
                $plugin['name'],
                $plugin['version'],
                $status
            ));
            if ($plugin['description']) {
                $output->writeln(sprintf('    %s', $plugin['description']));
            }
        }

        $output->writeln('');
        $output->writeln('Commands:');
        $output->writeln('  Enable:  php bin/console app:plugin --mode=<plugin-key>');
        $output->writeln('  Disable: php bin/console app:plugin --mode=<plugin-key> --disable');

        return Command::SUCCESS;
    }

    private function enablePlugins(OutputInterface $output, string $pluginKeys): int
    {
        $keys = array_map('trim', explode(',', $pluginKeys));
        $plugins = $this->getPluginsWithKeys();
        $availableKeys = array_column($plugins, 'key');

        $enabled = [];
        $notFound = [];

        foreach ($keys as $key) {
            if (!in_array($key, $availableKeys, true)) {
                $notFound[] = $key;
                continue;
            }

            // First install if not already installed
            if (!$this->pluginService->isInstalled($key)) {
                $this->pluginService->install($key);
            }

            $this->pluginService->enable($key);
            $enabled[] = $key;
        }

        if (!empty($notFound)) {
            $output->writeln(sprintf('Plugin(s) not found: %s', implode(', ', $notFound)));
            $output->writeln('Run php bin/console app:plugin --list to see available plugins.');
        }

        if (!empty($enabled)) {
            $output->writeln(sprintf('Enabled plugin(s): %s', implode(', ', $enabled)));
            $output->writeln('Plugin configuration updated in config/plugins.php');
        }

        return empty($notFound) ? Command::SUCCESS : Command::FAILURE;
    }

    private function disablePlugins(OutputInterface $output, string $pluginKeys): int
    {
        $keys = array_map('trim', explode(',', $pluginKeys));
        $plugins = $this->getPluginsWithKeys();
        $availableKeys = array_column($plugins, 'key');

        $disabled = [];
        $notFound = [];

        foreach ($keys as $key) {
            if (!in_array($key, $availableKeys, true)) {
                $notFound[] = $key;
                continue;
            }

            $this->pluginService->disable($key);
            $disabled[] = $key;
        }

        if (!empty($notFound)) {
            $output->writeln(sprintf('Plugin(s) not found: %s', implode(', ', $notFound)));
            $output->writeln('Run php bin/console app:plugin --list to see available plugins.');
        }

        if (!empty($disabled)) {
            $output->writeln(sprintf('Disabled plugin(s): %s', implode(', ', $disabled)));
            $output->writeln('Plugin configuration updated in config/plugins.php');
        }

        return empty($notFound) ? Command::SUCCESS : Command::FAILURE;
    }

    private function enableAllPlugins(OutputInterface $output): int
    {
        $plugins = $this->getPluginsWithKeys();
        $enabled = [];

        foreach ($plugins as $plugin) {
            $key = $plugin['key'];

            // First install if not already installed
            if (!$this->pluginService->isInstalled($key)) {
                $this->pluginService->install($key);
            }

            $this->pluginService->enable($key);
            $enabled[] = $key;
        }

        if (empty($enabled)) {
            $output->writeln('No plugins found to enable.');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Enabled all plugins: %s', implode(', ', $enabled)));
        $output->writeln('Plugin configuration updated in config/plugins.php');

        return Command::SUCCESS;
    }

    private function disableAllPlugins(OutputInterface $output): int
    {
        $config = [];
        $this->pluginService->setPluginConfig($config);

        $output->writeln('Disabled all plugins (cleared plugin configuration).');
        $output->writeln('Plugin configuration cleared in config/plugins.php');

        return Command::SUCCESS;
    }

    /**
     * Get plugins with their directory keys included.
     *
     * @return array<int, array{key: string, name: string, version: string, description: string, installed: bool, enabled: bool}>
     */
    private function getPluginsWithKeys(): array
    {
        $adminList = $this->pluginService->getAdminList();
        $pluginsWithKeys = [];

        // We need to re-scan the plugin directory to get the keys (directory names)
        // because getAdminList() doesn't include them
        $pluginDir = dirname(__DIR__, 2) . '/plugins';
        if (!is_dir($pluginDir)) {
            return [];
        }

        $directories = glob($pluginDir . '/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        foreach ($directories as $dir) {
            $key = basename($dir);
            $manifestFile = $dir . '/manifest.json';

            if (!file_exists($manifestFile)) {
                continue;
            }

            $manifestContent = file_get_contents($manifestFile);
            if ($manifestContent === false) {
                continue;
            }

            try {
                $manifestData = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            // Find matching entry from admin list to get installed/enabled status
            $installed = false;
            $enabled = false;
            foreach ($adminList as $plugin) {
                // Match by name from manifest
                if ($plugin['name'] === ($manifestData['name'] ?? $key)) {
                    $installed = $plugin['installed'];
                    $enabled = $plugin['enabled'];
                    break;
                }
            }

            $pluginsWithKeys[] = [
                'key' => $key,
                'name' => $manifestData['name'] ?? $key,
                'version' => $manifestData['version'] ?? '0.0.0',
                'description' => $manifestData['description'] ?? '',
                'installed' => $installed,
                'enabled' => $enabled,
            ];
        }

        return $pluginsWithKeys;
    }
}
