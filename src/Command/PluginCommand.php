<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Config\PluginService;
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
            'plugins',
            InputArgument::IS_ARRAY,
            'Plugin keys or "all"',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $plugins = array_values(array_filter(array_map(strval(...), (array) $input->getArgument('plugins')), static fn(string $plugin): bool => $plugin !== ''));

        if ($action === null) {
            return Command::SUCCESS;
        }

        if ($action !== 'enable' && $action !== 'disable') {
            return Command::FAILURE;
        }

        if ($plugins === []) {
            return Command::SUCCESS;
        }

        if ($action === 'enable') {
            return $this->enablePlugins($plugins);
        }

        return $this->disablePlugins($plugins);
    }

    /**
     * @param list<string> $plugins
     */
    private function enablePlugins(array $plugins): int
    {
        foreach (in_array('all', $plugins, true) ? $this->getAvailablePluginKeys() : $plugins as $key) {
            $this->pluginService->install($key);
            $this->pluginService->enable($key);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $plugins
     */
    private function disablePlugins(array $plugins): int
    {
        if (in_array('all', $plugins, true)) {
            return $this->disableAllPlugins();
        }

        foreach ($plugins as $key) {
            $this->pluginService->disable($key);
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
