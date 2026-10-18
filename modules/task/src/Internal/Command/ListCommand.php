<?php declare(strict_types=1);

namespace Module\Task\Internal\Command;

use Module\Task\Internal\CadenceTrigger;
use Module\Task\Internal\Registry;
use Module\Task\Internal\Repository\RunRepository;
use Override;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:task:list', description: 'Lists every scheduled task with its next and last run')]
class ListCommand extends Command
{
    private const string DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly Registry $registry,
        private readonly RunRepository $runRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now();
        $timezone = date_default_timezone_get();
        $latest = $this->runRepository->findLatestPerIdentifier();

        $table = new Table($output);
        $table->setHeaders(['Task', 'Cron', 'Offloaded', 'Next run', 'Last run', 'Status', 'Message']);
        foreach ($this->registry->all() as $identifier => $task) {
            $cadence = $task->getCadence();
            $next = new CadenceTrigger($identifier, $cadence, $timezone)->getNextRunDate($now);
            $last = $latest[$identifier] ?? null;
            $table->addRow([
                $identifier,
                $cadence->jitterSeconds > 0 ? sprintf('%s (+%ds)', $cadence->cron, $cadence->jitterSeconds) : $cadence->cron,
                $cadence->offloaded ? 'yes' : 'no',
                $next?->format(self::DATE_FORMAT) ?? '-',
                $last?->getStartedAt()->format(self::DATE_FORMAT) ?? '-',
                $last?->getStatus()->value ?? '-',
                $last?->getMessage() ?? '',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
