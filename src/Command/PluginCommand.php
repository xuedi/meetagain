<?php declare(strict_types=1);

namespace App\Command;

use App\Service\PluginService;
use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
        $mode = $input->getOption('mode');
        $disable = $input->getOption('disable');

        // List plugins (default)
        if (!$mode && !$disable) {
            return $this->listPlugins($io);
        }

        // Enable all plugins
        if ($mode === 'all') {
            return $this->enableAllPlugins($io);
        }

        // Disable all plugins (support both 'none' and 'no')
        if ($mode === 'none' || $mode === 'no') {
            return $this->disableAllPlugins($io);
        }

        // Plugin key provided
        if ($mode) {
            if ($disable) {
                return $this->disablePlugins($io, $mode);
            }

            return $this->enablePlugins($io, $mode);
        }

        $io->error('Invalid mode or option combination.');
        return Command::FAILURE;
    }

    private function listPlugins(SymfonyStyle $io): int
    {
        $plugins = $this->getPluginsWithKeys();

        if (empty($plugins)) {
            $io->warning('No plugins found in plugins/ directory.');

            return Command::SUCCESS;
        }

        $io->title('Available Plugins');

        $rows = [];
        foreach ($plugins as $plugin) {
            $status = $plugin['enabled']
                ? '<fg=green>✓ Enabled</>'
                : ($plugin['installed'] ? '<fg=yellow>○ Installed</>' : '<fg=gray>○ Available</>');

            $rows[] = [
                $plugin['key'],
                $plugin['name'],
                $plugin['version'],
                $status,
                $plugin['description'],
            ];
        }

        $io->table(['Key', 'Name', 'Version', 'Status', 'Description'], $rows);

        $io->newLine();
        $io->text('Commands:');
        $io->text('  Enable:  <comment>php bin/console app:plugin --mode=<plugin-key></comment>');
        $io->text('  Disable: <comment>php bin/console app:plugin --mode=<plugin-key> --disable</comment>');

        return Command::SUCCESS;
    }

    private function enablePlugins(SymfonyStyle $io, string $pluginKeys): int
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
            $io->error(sprintf('Plugin(s) not found: %s', implode(', ', $notFound)));
            $io->text('Run <comment>php bin/console app:plugin --list</comment> to see available plugins.');
        }

        if (!empty($enabled)) {
            $io->success(sprintf('Enabled plugin(s): %s', implode(', ', $enabled)));
            $io->note([
                'Plugin configuration updated in config/plugins.php',
                'Run migrations if needed: php bin/console doctrine:migrations:migrate',
                'Clear cache: php bin/console cache:clear',
            ]);
        }

        return empty($notFound) ? Command::SUCCESS : Command::FAILURE;
    }

    private function disablePlugins(SymfonyStyle $io, string $pluginKeys): int
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
            $io->error(sprintf('Plugin(s) not found: %s', implode(', ', $notFound)));
            $io->text('Run <comment>php bin/console app:plugin --list</comment> to see available plugins.');
        }

        if (!empty($disabled)) {
            $io->success(sprintf('Disabled plugin(s): %s', implode(', ', $disabled)));
            $io->note([
                'Plugin configuration updated in config/plugins.php',
                'Clear cache: php bin/console cache:clear',
            ]);
        }

        return empty($notFound) ? Command::SUCCESS : Command::FAILURE;
    }

    private function enableAllPlugins(SymfonyStyle $io): int
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
            $io->warning('No plugins found to enable.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Enabled all plugins: %s', implode(', ', $enabled)));
        $io->note([
            'Plugin configuration updated in config/plugins.php',
            'Run migrations if needed: php bin/console doctrine:migrations:migrate',
            'Clear cache: php bin/console cache:clear',
        ]);

        return Command::SUCCESS;
    }

    private function disableAllPlugins(SymfonyStyle $io): int
    {
        $config = [];
        $this->pluginService->setPluginConfig($config);

        $io->success('Disabled all plugins (cleared plugin configuration).');
        $io->note([
            'Plugin configuration cleared in config/plugins.php',
            'Clear cache: php bin/console cache:clear',
        ]);

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
