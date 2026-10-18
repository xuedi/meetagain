<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cron', description: 'Does nothing: the task workers and the scheduler replaced it')]
class CronCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('app:cron is replaced by the task workers and the scheduler (messenger:consume).');

        return Command::SUCCESS;
    }
}
