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
}
