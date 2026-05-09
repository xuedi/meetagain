<?php declare(strict_types=1);

namespace App\Service\Security;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Service\AppStateService;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

// TODO(2026-05-09) Aggregated retrospective reports are observation-only:
// hook in admin notification dispatch / threshold alerting once the
// notification routing for high retrospective threat levels is decided.
readonly class SecurityRetrospectiveCronTask implements CronTaskInterface
{
    private const int THROTTLE_SECONDS = 3600;

    public function __construct(
        private AppStateService $appState,
        private SecurityService $securityService,
    ) {}

    public function getIdentifier(): string
    {
        return 'security.retrospective-scan';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $lastRun = $this->appState->get(SecurityService::KEY_LAST_RETROSPECTIVE_RUN);
        if ($lastRun !== null) {
            $elapsed = (new DateTimeImmutable())->getTimestamp() - (int) $lastRun;
            if ($elapsed < self::THROTTLE_SECONDS) {
                $output->writeln(sprintf(
                    'SecurityRetrospectiveScan: skipped (last run %d min ago)',
                    (int) round($elapsed / 60),
                ));
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'Skipped - throttled');
            }
        }

        try {
            $reports = $this->securityService->runRetrospectiveScan();

            $parts = [];
            foreach ($reports as $report) {
                $parts[] = sprintf(
                    '%s: threat=%d (%s)',
                    $report->providerKey,
                    $report->threatLevel,
                    $report->summary,
                );
            }
            $message = $parts === [] ? 'No providers registered' : implode(' | ', $parts);
            $output->writeln('SecurityRetrospectiveScan: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('SecurityRetrospectiveScan exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
