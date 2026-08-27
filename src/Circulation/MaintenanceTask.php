<?php declare(strict_types=1);

namespace App\Circulation;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Service\AppStateService;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Override;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

readonly class MaintenanceTask implements CronTaskInterface
{
    public const string IDENTIFIER = 'circulation-maintenance';
    public const string STATE_KEY = 'circulation.maintenance_last_run';
    public const int NUDGE_AFTER_DAYS = 7;
    public const int CLOSE_AFTER_DAYS = 30;

    public function __construct(
        private QueueService $queue,
        private HandoverService $handovers,
        private CirculationHandoverRepository $handoverRepo,
        private CirculationCopyRepository $copies,
        private AppStateService $appState,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            if (!$this->isDue()) {
                return new CronTaskResult(self::IDENTIFIER, CronTaskStatus::ok, 'throttled');
            }

            $expiredOffers = $this->expireStaleOffers();
            $closed = $this->closeAbandonedHandovers();
            $matched = $this->matchFreeCopies();
            $nudged = count($this->handoverRepo->findOpenOlderThan(new DateTimeImmutable(sprintf('-%d days', self::NUDGE_AFTER_DAYS))));

            $this->appState->set(self::STATE_KEY, new DateTimeImmutable()->format(DATE_ATOM));

            $message = sprintf(
                '%d offers expired, %d handovers closed, %d copies offered, %d awaiting confirmation',
                $expiredOffers,
                $closed,
                $matched,
                $nudged,
            );
            $output->writeln('CirculationMaintenance: ' . $message);

            return new CronTaskResult(self::IDENTIFIER, CronTaskStatus::ok, $message);
        } catch (Throwable $exception) {
            $output->writeln('CirculationMaintenance exception: ' . $exception->getMessage());

            return new CronTaskResult(self::IDENTIFIER, CronTaskStatus::exception, $exception->getMessage());
        }
    }

    private function isDue(): bool
    {
        $last = $this->appState->get(self::STATE_KEY);
        if ($last === null) {
            return true;
        }

        return new DateTimeImmutable($last) < new DateTimeImmutable('-1 day');
    }

    private function expireStaleOffers(): int
    {
        $expired = 0;
        foreach ($this->queue->findStaleOffers() as $request) {
            $copy = $request->getOfferedCopy();
            foreach ($this->handoverRepo->findByCopyIds($copy === null ? [] : [(int) $copy->getId()]) as $handover) {
                if ($handover->getRequest()?->getId() !== $request->getId()) {
                    continue;
                }

                $this->handovers->expire($handover);
            }

            $this->queue->passOn($request, $copy);
            if ($copy !== null) {
                $this->queue->offerToNext($copy);
            }
            $expired++;
        }

        return $expired;
    }

    private function closeAbandonedHandovers(): int
    {
        $closed = 0;
        foreach ($this->handoverRepo->findOpenOlderThan(new DateTimeImmutable(sprintf('-%d days', self::CLOSE_AFTER_DAYS))) as $handover) {
            $this->handovers->expire($handover);
            $closed++;
        }

        return $closed;
    }

    private function matchFreeCopies(): int
    {
        $matched = 0;
        foreach ($this->copies->findAllOrdered() as $copy) {
            if ($this->queue->offerToNext($copy) === null) {
                continue;
            }

            $matched++;
        }

        return $matched;
    }
}
