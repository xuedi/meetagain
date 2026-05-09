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

    /**
     * @return list<RateLimitLog>
     */
    public function findFiltered(
        int $limit,
        ?DateTimeImmutable $since,
        ?string $ip = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
    ): array {
        $qb = $this
            ->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('r.createdAt >= :since')->setParameter('since', $since);
        }
        if ($ip !== null && $ip !== '') {
            $qb->andWhere('r.ip = :ip')->setParameter('ip', $ip);
        }
        if ($from !== null) {
            $qb->andWhere('r.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('r.createdAt <= :to')->setParameter('to', $to);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<RateLimitLog>
     */
    public function findRowsAfterIdUpToByLimiter(
        int $lastId,
        DateTimeImmutable $cutoff,
        int $limit,
        string $limiter,
        bool $exclude = false,
    ): array {
        $op = $exclude ? '<>' : '=';

        return array_values(
            $this
                ->createQueryBuilder('r')
                ->where('r.id > :lastId')
                ->andWhere('r.createdAt <= :cutoff')
                ->andWhere('r.limiter ' . $op . ' :limiter')
                ->orderBy('r.id', 'ASC')
                ->setParameter('lastId', $lastId)
                ->setParameter('cutoff', $cutoff)
                ->setParameter('limiter', $limiter)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
        );
    }

    public function countRowsAfterIdUpToByLimiter(
        int $lastId,
        DateTimeImmutable $cutoff,
        string $limiter,
        bool $exclude = false,
    ): int {
        $op = $exclude ? '<>' : '=';

        return (int) $this
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.id > :lastId')
            ->andWhere('r.createdAt <= :cutoff')
            ->andWhere('r.limiter ' . $op . ' :limiter')
            ->setParameter('lastId', $lastId)
            ->setParameter('cutoff', $cutoff)
            ->setParameter('limiter', $limiter)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
