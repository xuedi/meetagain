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

#[AsCommand(
    name: 'app:plugin',
    description: 'List, enable, or disable plugins'
)]
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
            ->addArgument('plugin', InputArgument::OPTIONAL, 'Plugin key to enable/disable')
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enable the specified plugin')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disable the specified plugin')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available plugins (default if no options given)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pluginKey = $input->getArgument('plugin');
        $enable = $input->getOption('enable');
        $disable = $input->getOption('disable');
        $list = $input->getOption('list');

        // Enable plugin
        if ($enable && $pluginKey) {
            return $this->enablePlugin($io, $pluginKey);
        }

        // Disable plugin
        if ($disable && $pluginKey) {
            return $this->disablePlugin($io, $pluginKey);
        }

        // List plugins (default)
        return $this->listPlugins($io);
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
            $status = $plugin['enabled'] ? '<fg=green>✓ Enabled</>' :
                     ($plugin['installed'] ? '<fg=yellow>○ Installed</>' : '<fg=gray>○ Available</>');

            $rows[] = [
                $plugin['key'],
                $plugin['name'],
                $plugin['version'],
                $status,
                $plugin['description'],
            ];
        }

        $io->table(
            ['Key', 'Name', 'Version', 'Status', 'Description'],
            $rows
        );

        $io->newLine();
        $io->text('Commands:');
        $io->text('  Enable:  <comment>php bin/console app:plugin <plugin-key> --enable</comment>');
        $io->text('  Disable: <comment>php bin/console app:plugin <plugin-key> --disable</comment>');

        return Command::SUCCESS;
    }

    private function enablePlugin(SymfonyStyle $io, string $pluginKey): int
    {
        $plugins = $this->getPluginsWithKeys();
        $pluginExists = false;

        foreach ($plugins as $plugin) {
            if ($plugin['key'] === $pluginKey) {
                $pluginExists = true;
                break;
            }
        }

        if (!$pluginExists) {
            $io->error("Plugin '{$pluginKey}' not found.");
            $io->text('Run <comment>php bin/console app:plugin --list</comment> to see available plugins.');

            return Command::FAILURE;
        }

        // First install if not already installed
        if (!$this->pluginService->isInstalled($pluginKey)) {
            $this->pluginService->install($pluginKey);
        }

        $this->pluginService->enable($pluginKey);
        $io->success("Plugin '{$pluginKey}' has been enabled.");
        $io->note([
            'Plugin configuration updated in config/plugins.php',
            'Run migrations if needed: php bin/console doctrine:migrations:migrate',
            'Clear cache: php bin/console cache:clear',
        ]);

        return Command::SUCCESS;
    }

    private function disablePlugin(SymfonyStyle $io, string $pluginKey): int
    {
        $plugins = $this->getPluginsWithKeys();
        $pluginExists = false;

        foreach ($plugins as $plugin) {
            if ($plugin['key'] === $pluginKey) {
                $pluginExists = true;
                break;
            }
        }

        if (!$pluginExists) {
            $io->error("Plugin '{$pluginKey}' not found.");
            $io->text('Run <comment>php bin/console app:plugin --list</comment> to see available plugins.');

            return Command::FAILURE;
        }

        $this->pluginService->disable($pluginKey);
        $io->success("Plugin '{$pluginKey}' has been disabled.");
        $io->note([
            'Plugin configuration updated in config/plugins.php',
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
