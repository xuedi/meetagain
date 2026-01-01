<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailQueue;
use App\Entity\EmailQueueStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailQueue>
 */
class EmailQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailQueue::class);
    }

    public function getPendingCount(): int
    {
        return (int) $this->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.status = :status')
            ->setParameter('status', EmailQueueStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStaleCount(int $minutes = 60): int
    {
        return (int) $this->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.status = :status')
            ->andWhere('eq.createdAt < :threshold')
            ->setParameter('status', EmailQueueStatus::Pending)
            ->setParameter('threshold', new DateTime('-' . $minutes . ' minutes'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get pending emails grouped by template type.
     *
     * @return array<string, int> Template name => count
     */
    public function getPendingByTemplate(): array
    {
        $result = $this->createQueryBuilder('eq')
            ->select('eq.template', 'COUNT(eq.id) as count')
            ->where('eq.status = :status')
            ->setParameter('status', EmailQueueStatus::Pending)
            ->groupBy('eq.template')
            ->getQuery()
            ->getArrayResult();

        $breakdown = [];
        foreach ($result as $row) {
            $template = $row['template']->value ?? 'unknown';
            $breakdown[$template] = (int) $row['count'];
        }

        return $breakdown;
    }

    /**
     * Get delivery stats for the dashboard.
     *
     * @return array{total: int, sent: int, failed: int}
     */
    public function getDeliveryStats(DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('eq')
            ->select(
                'COUNT(eq.id) as total',
                'SUM(CASE WHEN eq.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN eq.status = :failed THEN 1 ELSE 0 END) as failed'
            )
            ->where('eq.sendAt IS NOT NULL OR eq.status = :failed')
            ->andWhere('eq.createdAt > :since')
            ->setParameter('sent', EmailQueueStatus::Sent)
            ->setParameter('failed', EmailQueueStatus::Failed)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'sent' => (int) $result['sent'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * Calculate delivery success rate.
     */
    public function getDeliverySuccessRate(DateTimeImmutable $since): float
    {
        $stats = $this->getDeliveryStats($since);

        if ($stats['total'] === 0) {
            return 100.0;
        }

        return round(($stats['sent'] / $stats['total']) * 100, 1);
    }
}
