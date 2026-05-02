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
     * @param list<string>|null $statuses Filter by exact status values; null means no status filter.
     * @return CronLog[]
     */
    public function findRecent(int $limit = 200, ?DateTimeImmutable $since = null, ?array $statuses = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.runAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('c.runAt >= :since')
                ->setParameter('since', $since);
        }

        if ($statuses !== null && $statuses !== []) {
            $qb->andWhere('c.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
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

    /**
     * @param list<string>|null $statuses
     */
    public function countFiltered(?DateTimeImmutable $since = null, ?array $statuses = null): int
    {
        $qb = $this->createQueryBuilder('c')->select('COUNT(c.id)');

        if ($since !== null) {
            $qb->andWhere('c.runAt >= :since')->setParameter('since', $since);
        }

        if ($statuses !== null && $statuses !== []) {
            $qb->andWhere('c.status IN (:statuses)')->setParameter('statuses', $statuses);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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
