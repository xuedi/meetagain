<?php declare(strict_types=1);

namespace App\Command;

use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugin:list', description: 'List all available plugins')]
class PluginListCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins = $this->getPluginsWithKeys();

        if ($plugins === []) {
            $output->writeln('No plugins found in plugins/ directory.');

            return Command::SUCCESS;
        }

        $output->writeln('Available Plugins:');
        $output->writeln('');

        foreach ($plugins as $plugin) {
            $status = $plugin['enabled'] ? 'Enabled' : ($plugin['installed'] ? 'Installed' : 'Available');

            $output->writeln(sprintf(
                '  %s - %s (%s) [%s]',
                $plugin['key'],
                $plugin['name'],
                $plugin['version'],
                $status,
            ));
            if ($plugin['description']) {
                $output->writeln(sprintf('    %s', $plugin['description']));
            }
        }

        $output->writeln('');
        $output->writeln('Commands:');
        $output->writeln('  Enable:  php bin/console app:plugin enable <plugin-key>');
        $output->writeln('  Disable: php bin/console app:plugin disable <plugin-key>');

        return Command::SUCCESS;
    }

    /**
     * Get plugins with their directory keys and status.
     *
     * @return array<int, array{key: string, name: string, version: string, description: string, installed: bool, enabled: bool}>
     */
    private function getPluginsWithKeys(): array
    {
        $pluginsWithKeys = [];
        $pluginDir = dirname(__DIR__, 2) . '/plugins';

        if (!is_dir($pluginDir)) {
            return [];
        }

        $directories = glob($pluginDir . '/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        // Load plugin config to check installed/enabled status
        $configFile = dirname(__DIR__, 2) . '/config/plugins.php';
        $config = [];
        if (file_exists($configFile)) {
            $config = include $configFile;
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

            $pluginsWithKeys[] = [
                'key' => $key,
                'name' => $manifestData['name'] ?? $key,
                'version' => $manifestData['version'] ?? '0.0.0',
                'description' => $manifestData['description'] ?? '',
                'installed' => isset($config[$key]),
                'enabled' => ($config[$key] ?? false) === true,
            ];
        }

        return $pluginsWithKeys;
    }
}
