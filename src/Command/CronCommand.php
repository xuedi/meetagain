<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CleanupService;
use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cron', description: 'cron manager to be called often, maybe every 5 min or so')]
class CronCommand extends Command
{
    public function __construct(
        private readonly EmailService $mailService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('Send out queued emails ... ');
        //$this->mailService->sendQueue();
        $output->writeln('OK');

        return Command::SUCCESS;
    }
}
