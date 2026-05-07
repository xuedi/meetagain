<?php declare(strict_types=1);

namespace App\Service\Security;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Service\AppStateService;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

readonly class UrlProbingAggregatorCronTask implements CronTaskInterface
{
    private const string KEY_LAST_RUN = 'cron.url_probing_aggregator.last_run';
    private const int THROTTLE_SECONDS = 8 * 3600;

    public function __construct(
        private AppStateService $appState,
        private UrlProbingAggregator $aggregator,
    ) {}

    public function getIdentifier(): string
    {
        return 'security.aggregate-url-probing';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $lastRun = $this->appState->get(self::KEY_LAST_RUN);
        if ($lastRun !== null) {
            $elapsed = (new DateTimeImmutable())->getTimestamp() - (int) $lastRun;
            if ($elapsed < self::THROTTLE_SECONDS) {
                $output->writeln(sprintf(
                    'UrlProbingAggregator: skipped (last run %d min ago)',
                    (int) round($elapsed / 60),
                ));
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'Skipped - throttled');
            }
        }

        try {
            $stats = $this->aggregator->aggregate();
            $this->appState->set(self::KEY_LAST_RUN, (string) (new DateTimeImmutable())->getTimestamp());
            $message = sprintf(
                'Considered %d raw rows across %d IPs, created %d incidents (%d dropped as below threshold)',
                $stats['considered'],
                $stats['ipsProcessed'],
                $stats['incidents'],
                $stats['dropped'],
            );
            $output->writeln('UrlProbingAggregator: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('UrlProbingAggregator exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
