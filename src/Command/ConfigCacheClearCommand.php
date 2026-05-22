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
    name: 'app:cache:config:clear',
    description: 'Clear the config cache pool (call after migrations or fixture loads that write config rows directly).',
)]
class ConfigCacheClearCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'cache.config')]
        private readonly CacheItemPoolInterface $configPool,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->configPool->clear();
        $output->writeln('<info>config cache pool cleared</info>');

        return Command::SUCCESS;
    }
}
