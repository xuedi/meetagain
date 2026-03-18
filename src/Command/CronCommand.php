<?php declare(strict_types=1);

namespace App\Command;

use App\CronTaskInterface;
use App\Service\Admin\CommandExecutionService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:cron', description: 'cron manager to be called often, maybe every 5 min or so')]
class CronCommand extends LoggedCommand
{
    use LockableTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
        CommandExecutionService $commandExecutionService,
        #[AutowireIterator(CronTaskInterface::class)]
        private readonly iterable $cronTasks = [],
    ) {
        parent::__construct($commandExecutionService);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            return Command::SUCCESS;
        }

        foreach ($this->cronTasks as $task) {
            try {
                $task->runCronTask($output);
            } catch (Exception $e) {
                $this->logger->error('Cron task failed', [
                    'task' => $task::class,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->release();

        return Command::SUCCESS;
    }
}
