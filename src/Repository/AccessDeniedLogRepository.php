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
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC')->setMaxResults($limit);

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

        return array_map(static fn(array $row): array => [
            'number' => (int) $row['number'],
            'url' => (string) $row['url'],
        ], $rows);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
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

    /**
     * @return list<AccessDeniedLog>
     */
    public function findRowsAfterIdUpTo(int $lastId, DateTimeImmutable $cutoff, int $limit): array
    {
        return array_values(
            $this
                ->createQueryBuilder('a')
                ->where('a.id > :lastId')
                ->andWhere('a.createdAt <= :cutoff')
                ->orderBy('a.id', 'ASC')
                ->setParameter('lastId', $lastId)
                ->setParameter('cutoff', $cutoff)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(),
        );
    }

    public function countRowsAfterIdUpTo(int $lastId, DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.id > :lastId')
            ->andWhere('a.createdAt <= :cutoff')
            ->setParameter('lastId', $lastId)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestUnlinkedForOffender(string $ip): ?AccessDeniedLog
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.ip = :ip')
            ->andWhere('a.incident IS NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->setParameter('ip', $ip)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AccessDeniedLog>
     */
    public function findFiltered(
        int $limit,
        ?DateTimeImmutable $since,
        ?string $ip = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC')->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('a.createdAt >= :since')->setParameter('since', $since);
        }
        if ($ip !== null && $ip !== '') {
            $qb->andWhere('a.ip = :ip')->setParameter('ip', $ip);
        }
        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $to);
        }

        return array_values($qb->getQuery()->getResult());
    }
}
