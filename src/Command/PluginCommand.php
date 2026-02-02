<?php declare(strict_types=1);

namespace App\Command;

use App\Service\PluginService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugin', description: 'Enable or disable plugins')]
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
        $this->addArgument('action', InputArgument::OPTIONAL, 'Action: enable or disable')->addArgument(
            'plugin',
            InputArgument::OPTIONAL,
            'Plugin key or "all"',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $plugin = $input->getArgument('plugin');

        // No arguments - do nothing
        if ($action === null) {
            return Command::SUCCESS;
        }

        // Validate action
        if ($action !== 'enable' && $action !== 'disable') {
            return Command::FAILURE;
        }

        // No plugin argument - do nothing (enable/disable zero plugins)
        if ($plugin === null || $plugin === '') {
            return Command::SUCCESS;
        }

        // Route to appropriate method
        if ($action === 'enable') {
            return $this->enablePlugins($plugin);
        }

        return $this->disablePlugins($plugin);
    }

    private function enablePlugins(string $plugin): int
    {
        if ($plugin === 'all') {
            return $this->enableAllPlugins();
        }

        $this->pluginService->install($plugin);
        $this->pluginService->enable($plugin);

        return Command::SUCCESS;
    }

    private function disablePlugins(string $plugin): int
    {
        if ($plugin === 'all') {
            return $this->disableAllPlugins();
        }

        $this->pluginService->disable($plugin);

        return Command::SUCCESS;
    }

    private function enableAllPlugins(): int
    {
        $pluginKeys = $this->getAvailablePluginKeys();

        foreach ($pluginKeys as $key) {
            $this->pluginService->install($key);
            $this->pluginService->enable($key);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function getAvailablePluginKeys(): array
    {
        $pluginDir = dirname(__DIR__, 2) . '/plugins';
        if (!is_dir($pluginDir)) {
            return [];
        }

        $directories = glob($pluginDir . '/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        $keys = [];
        foreach ($directories as $dir) {
            $key = basename($dir);
            $manifestFile = $dir . '/manifest.json';

            if (file_exists($manifestFile)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function disableAllPlugins(): int
    {
        $this->pluginService->setPluginConfig([]);

        return Command::SUCCESS;
    }
}
