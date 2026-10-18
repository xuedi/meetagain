<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Config\PluginService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugin:list', description: 'List the installed plugins and whether each is switched on')]
class PluginListCommand extends Command
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins = $this->pluginService->getAdminList();
        if ($plugins === []) {
            $output->writeln('No plugins found in plugins/ directory.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Key', 'Name', 'Version', 'State', 'Requires']);
        foreach ($plugins as $plugin) {
            $table->addRow([
                $plugin['key'],
                $plugin['name'],
                $plugin['version'],
                $plugin['system'] ? 'always on' : ($plugin['enabled'] ? 'on' : 'off'),
                implode(', ', $plugin['requires']),
            ]);
        }
        $table->render();
        $output->writeln('Switch one with: php bin/console app:plugin:toggle on|off <plugin-key>');

        return Command::SUCCESS;
    }
}
