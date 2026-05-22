<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:cache:app-state:clear',
    description: 'Clear the app_state cache pool (call after migrations or fixture loads that write app_state rows directly).',
)]
class AppStateCacheClearCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'cache.app_state')]
        private readonly CacheItemPoolInterface $appStatePool,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appStatePool->clear();
        $output->writeln('<info>app_state cache pool cleared</info>');

        return Command::SUCCESS;
    }
}
