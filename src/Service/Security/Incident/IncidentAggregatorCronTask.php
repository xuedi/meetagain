<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Service\AppStateService;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

readonly class IncidentAggregatorCronTask implements CronTaskInterface
{
    public const string KEY_LAST_RUN = 'cron.incident_aggregator.last_run';
    private const int THROTTLE_SECONDS = 8 * 3600;

    public function __construct(
        private AppStateService $appState,
        private IncidentAggregator $aggregator,
    ) {}

    public function getIdentifier(): string
    {
        return 'security.aggregate-incidents';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $lastRun = $this->appState->get(self::KEY_LAST_RUN);
        if ($lastRun !== null) {
            $elapsed = (new DateTimeImmutable())->getTimestamp() - (int) $lastRun;
            if ($elapsed < self::THROTTLE_SECONDS) {
                $output->writeln(sprintf(
                    'IncidentAggregator: skipped (last run %d min ago)',
                    (int) round($elapsed / 60),
                ));
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'Skipped - throttled');
            }
        }

        try {
            $allStats = $this->aggregator->aggregate();
            $this->appState->set(self::KEY_LAST_RUN, (string) (new DateTimeImmutable())->getTimestamp());

            $parts = [];
            foreach ($allStats as $s) {
                $parts[] = sprintf(
                    '%s: %d considered / %d ips / %d incidents',
                    $s->sourceKey,
                    $s->considered,
                    $s->ipsTouched,
                    $s->incidentsTouched,
                );
            }
            $message = implode(' | ', $parts);
            $output->writeln('IncidentAggregator: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('IncidentAggregator exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
