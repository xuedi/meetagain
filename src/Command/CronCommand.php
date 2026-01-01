<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CommandExecutionService;
use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:cron', description: 'cron manager to be called often, maybe every 5 min or so')]
class CronCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly EmailService $mailService,
        private readonly CommandExecutionService $commandExecutionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            return Command::SUCCESS;
        }

        $log = $this->commandExecutionService->start('app:cron');

        try {
            $output->write('Send out queued emails ... ');
            $this->mailService->sendQueue();
            $output->writeln('OK');

            $this->commandExecutionService->complete($log, 0, 'Email queue processed');
        } catch (Throwable $e) {
            $this->commandExecutionService->fail($log, $e->getMessage());
            $this->release();

            throw $e;
        }

        $this->release();

        return Command::SUCCESS;
    }
}
