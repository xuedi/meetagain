<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CronLog;
use App\Enum\CronTaskStatus;
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
    public function findRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('c.runAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return CronLog[]
     */
    public function findRecentProblems(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status != :ok')
            ->setParameter('ok', CronTaskStatus::ok->value)
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('c.runAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function countProblems(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status != :ok')
            ->setParameter('ok', CronTaskStatus::ok->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findMostRecent(): ?CronLog
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
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
