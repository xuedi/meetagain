<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailDeliveryLog;
use App\Entity\EmailDeliveryStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailDeliveryLog>
 */
class EmailDeliveryLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailDeliveryLog::class);
    }

    /**
     * Get delivery stats for the dashboard.
     *
     * @return array{total: int, sent: int, failed: int}
     */
    public function getStats(DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('e')
            ->select(
                'COUNT(e.id) as total',
                'SUM(CASE WHEN e.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN e.status IN (:failed, :bounced) THEN 1 ELSE 0 END) as failed'
            )
            ->where('e.sentAt > :since')
            ->setParameter('since', $since)
            ->setParameter('sent', EmailDeliveryStatus::Sent)
            ->setParameter('failed', EmailDeliveryStatus::Failed)
            ->setParameter('bounced', EmailDeliveryStatus::Bounced)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'sent' => (int) $result['sent'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * Get failed delivery count for the given period.
     */
    public function getFailedCount(DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.status IN (:statuses)')
            ->andWhere('e.sentAt > :since')
            ->setParameter('statuses', [EmailDeliveryStatus::Failed, EmailDeliveryStatus::Bounced])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get recent failed deliveries.
     *
     * @return EmailDeliveryLog[]
     */
    public function getRecentFailed(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.emailQueue', 'q')
            ->addSelect('q')
            ->where('e.status IN (:statuses)')
            ->setParameter('statuses', [EmailDeliveryStatus::Failed, EmailDeliveryStatus::Bounced])
            ->orderBy('e.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate delivery success rate.
     */
    public function getSuccessRate(DateTimeImmutable $since): float
    {
        $stats = $this->getStats($since);

        if ($stats['total'] === 0) {
            return 100.0;
        }

        return round(($stats['sent'] / $stats['total']) * 100, 1);
    }
}
