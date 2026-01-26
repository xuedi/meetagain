<?php declare(strict_types=1);

namespace App\Command;

use App\Plugin;
use App\Service\PluginService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:plugin:post-fixtures', description: 'Execute post-fixture tasks for all enabled plugins')]
class PluginPostFixturesCommand extends Command
{
    public function __construct(
        private readonly PluginService $pluginService,
        #[AutowireIterator(Plugin::class)]
        private readonly iterable $plugins,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $hasErrors = false;

        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            try {
                $output->write(sprintf('Running %s plugin post-fixtures ... ', $plugin->getPluginKey()));
                $plugin->postFixtures($output);
                $output->writeln('OK');
            } catch (Exception $e) {
                $hasErrors = true;
                $output->writeln(sprintf('<error>FAILED: %s</error>', $e->getMessage()));
                $this->logger->error('Plugin post-fixtures failed', [
                    'plugin' => $plugin->getPluginKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
