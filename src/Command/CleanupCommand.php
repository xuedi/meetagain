<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CleanupService;
use App\Service\CommandExecutionService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:cleanup', description: 'does certain cleanup tasks')]
class CleanupCommand extends Command
{
    public function __construct(
        private readonly CleanupService $cleanupService,
        private readonly CommandExecutionService $commandExecutionService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $log = $this->commandExecutionService->start('app:cleanup');

        try {
            $output->write('Clean image cache ... ');
            $this->cleanupService->removeImageCache();
            $output->writeln('OK');

            $output->write('Clean registrations ... ');
            $this->cleanupService->removeGhostedRegistrations();
            $output->writeln('OK');

            $this->commandExecutionService->complete($log, 0, 'Cleanup completed');
        } catch (Throwable $e) {
            $this->commandExecutionService->fail($log, $e->getMessage());

            throw $e;
        }

        return Command::SUCCESS;
    }
}
