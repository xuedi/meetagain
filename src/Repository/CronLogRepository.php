<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CronLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CronLog>
 */
class CronLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CronLog::class);
    }

    /**
     * @return CronLog[]
     */
    public function findRecent(int $limit = 200): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findMostRecent(): ?CronLog
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.runAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
