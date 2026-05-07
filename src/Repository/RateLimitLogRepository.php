<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\RateLimitLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RateLimitLog>
 */
class RateLimitLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RateLimitLog::class);
    }

    /**
     * @return list<RateLimitLog>
     */
    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('r.createdAt >= :since')->setParameter('since', $since);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<array{number: int, limiter: string, ip: string}>
     */
    public function getTop100(?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('r')
            ->select('COUNT(r.id) AS number', 'r.limiter', 'r.ip')
            ->groupBy('r.limiter', 'r.ip')
            ->orderBy('number', 'DESC')
            ->setMaxResults(100);

        if ($since !== null) {
            $qb->where('r.createdAt >= :since')->setParameter('since', $since);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            static fn(array $row): array => [
                'number' => (int) $row['number'],
                'limiter' => (string) $row['limiter'],
                'ip' => (string) $row['ip'],
            ],
            $rows,
        );
    }

    public function countAll(): int
    {
        return (int) $this
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSince(DateTimeImmutable $since): int
    {
        return (int) $this
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
