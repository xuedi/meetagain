<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessDeniedLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessDeniedLog>
 */
class AccessDeniedLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessDeniedLog::class);
    }

    /**
     * @return list<AccessDeniedLog>
     */
    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('a.createdAt >= :since')->setParameter('since', $since);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<array{number: int, url: string}>
     */
    public function getTop100(?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id) AS number', 'a.url')
            ->groupBy('a.url')
            ->orderBy('number', 'DESC')
            ->setMaxResults(100);

        if ($since !== null) {
            $qb->where('a.createdAt >= :since')->setParameter('since', $since);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            static fn(array $row): array => ['number' => (int) $row['number'], 'url' => (string) $row['url']],
            $rows,
        );
    }

    public function countAll(): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSince(DateTimeImmutable $since): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
