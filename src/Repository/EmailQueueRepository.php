<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailQueue;
use DateTime;
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
            ->where('eq.sendAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStaleCount(int $minutes = 60): int
    {
        return (int) $this->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.sendAt IS NULL')
            ->andWhere('eq.createdAt < :threshold')
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
            ->where('eq.sendAt IS NULL')
            ->groupBy('eq.template')
            ->getQuery()
            ->getArrayResult();

        $breakdown = [];
        foreach ($result as $row) {
            $template = $row['template'] ?? 'unknown';
            $breakdown[$template] = (int) $row['count'];
        }

        return $breakdown;
    }
}
