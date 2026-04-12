<?php declare(strict_types=1);

namespace App\Command;

use App\CronTaskInterface;
use App\Entity\CronLog;
use App\Enum\CronTaskStatus;
use App\Service\Admin\CommandExecutionService;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $em,
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

        /** @var array<int, array{result: CronTaskResult, duration_ms: int}> $timed */
        $timed = [];
        $totalStart = hrtime(true);

        foreach ($this->cronTasks as $task) {
            $taskStart = hrtime(true);
            $result = $task->runCronTask($output);
            $durationMs = (int) ((hrtime(true) - $taskStart) / 1_000_000);
            $timed[] = ['result' => $result, 'duration_ms' => $durationMs];
        }

        $totalDurationMs = (int) ((hrtime(true) - $totalStart) / 1_000_000);

        $aggregated = CronTaskStatus::ok;
        foreach ($timed as $entry) {
            $aggregated = $aggregated->worst($entry['result']->status);
        }

        $tasks = array_map(
            fn(array $entry) => [
                'identifier'  => $entry['result']->identifier,
                'status'      => $entry['result']->status->value,
                'message'     => $entry['result']->message,
                'duration_ms' => $entry['duration_ms'],
            ],
            $timed,
        );

        $log = new CronLog(
            runAt: new DateTimeImmutable(),
            status: $aggregated,
            durationMs: $totalDurationMs,
            tasks: $tasks,
        );
        $this->em->persist($log);
        $this->em->flush();

        $this->release();

        return Command::SUCCESS;
    }
}
