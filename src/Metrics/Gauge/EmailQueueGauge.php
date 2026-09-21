<?php declare(strict_types=1);

namespace App\Metrics\Gauge;

use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use App\Repository\EmailQueueRepository;
use DateTimeImmutable;

final readonly class EmailQueueGauge implements GaugeInterface
{
    public function __construct(
        private EmailQueueRepository $repository,
    ) {}

    public function collect(): iterable
    {
        $now = new DateTimeImmutable();
        $oldest = $this->repository->findOldestPendingCreatedAt();

        yield new Point('email_queue', [
            'pending' => $this->repository->getPendingCount(),
            'oldest_pending_age_s' => $oldest === null ? 0 : max(0, $now->getTimestamp() - $oldest->getTimestamp()),
            'failed_24h' => $this->repository->getDeliveryStats($now->modify('-24 hours'))['failed'],
        ]);
    }
}
