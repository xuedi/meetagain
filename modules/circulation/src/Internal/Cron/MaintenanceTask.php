<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Cron;

use DateTimeImmutable;
use Module\Circulation\Internal\HandoverService;
use Module\Circulation\Internal\QueueService;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Override;
use Throwable;

readonly class MaintenanceTask implements TaskInterface
{
    public const string IDENTIFIER = 'circulation-maintenance';
    public const int NUDGE_AFTER_DAYS = 7;
    public const int CLOSE_AFTER_DAYS = 30;

    public function __construct(
        private QueueService $queue,
        private HandoverService $handovers,
        private HandoverRepository $handoverRepo,
        private CopyRepository $copies,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('40 3 * * *', jitterSeconds: 1800, offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf(
            'Keeps lending queues moving. Once a night, an offered copy that the member in line has not picked up within %d days is passed on to the next person in the queue, a handover still unconfirmed after %d days is closed, and every free copy with someone waiting is offered to the first in line. The run message also counts handovers open for more than %d days.',
            QueueService::OFFER_WINDOW_DAYS,
            self::CLOSE_AFTER_DAYS,
            self::NUDGE_AFTER_DAYS,
        );
    }

    #[Override]
    public function run(): Result
    {
        try {
            $expiredOffers = $this->expireStaleOffers();
            $closed = $this->closeAbandonedHandovers();
            $matched = $this->matchFreeCopies();
            $nudged = count($this->handoverRepo->findOpenOlderThan(new DateTimeImmutable(sprintf('-%d days', self::NUDGE_AFTER_DAYS))));

            $message = sprintf(
                '%d offers expired, %d handovers closed, %d copies offered, %d awaiting confirmation',
                $expiredOffers,
                $closed,
                $matched,
                $nudged,
            );

            return new Result(Status::ok, $message);
        } catch (Throwable $exception) {
            return new Result(Status::exception, $exception->getMessage());
        }
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
