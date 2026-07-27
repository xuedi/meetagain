<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotFoundLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotFoundLog>
 */
class NotFoundLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotFoundLog::class);
    }

    public function getWeekSummary(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $unhydratedList = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('DATE_FORMAT(nf.createdAt, \'%W\') AS groupedDay', 'COUNT(nf.id) as number')
            ->from(NotFoundLog::class, 'nf')
            ->where('nf.createdAt > :startDate AND nf.createdAt < :endDate')
            ->groupBy('groupedDay')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getArrayResult();

        $list = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
        foreach ($unhydratedList as $item) {
            $list[$item['groupedDay']] = $item['number'];
        }

        return $list;
    }

    public function getTop100(?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('n')->select('COUNT(n.id) as number', 'n.url')->groupBy('n.url')->orderBy('number', 'DESC')->setMaxResults(100);

        if ($since !== null) {
            $qb->where('n.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return list<array{value: string, number: int}>
     */
    public function getTopUrls(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getTopValues('url', $limit, $since, false);
    }

    /**
     * @return list<array{value: string, number: int}>
     */
    public function getTopIps(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getTopValues('ip', $limit, $since, false);
    }

    /**
     * @return list<array{value: string, number: int}>
     */
    public function getTopReferers(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getTopValues('referer', $limit, $since, true);
    }

    /**
     * @return list<array{value: string, number: int}>
     */
    public function getTopUserAgents(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getTopValues('userAgent', $limit, $since, true);
    }

    /**
     * @return list<array{bucket: string, number: int}>
     */
    public function getDailyCounts(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getBucketCounts('%Y-%m-%d', $limit, $since);
    }

    /**
     * @return list<array{bucket: string, number: int}>
     */
    public function getHourlyCounts(int $limit, ?DateTimeImmutable $since = null): array
    {
        return $this->getBucketCounts('%Y-%m-%d %H:00', $limit, $since);
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, int>
     */
    public function countByUrls(array $urls, ?DateTimeImmutable $since = null): array
    {
        if ($urls === []) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('n')
            ->select('n.url AS url', 'COUNT(n.id) AS number')
            ->andWhere('n.url IN (:urls)')
            ->groupBy('n.url')
            ->setParameter('urls', $urls);

        if ($since !== null) {
            $qb->andWhere('n.createdAt >= :since')->setParameter('since', $since);
        }

        $counts = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[(string) $row['url']] = (int) $row['number'];
        }

        return $counts;
    }

    public function countDistinctUrls(?DateTimeImmutable $since = null): int
    {
        return $this->countDistinct('url', $since);
    }

    public function countDistinctIps(?DateTimeImmutable $since = null): int
    {
        return $this->countDistinct('ip', $since);
    }

    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('n')->orderBy('n.createdAt', 'DESC')->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('n.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('n')->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
    }

    public function countSince(DateTimeImmutable $since): int
    {
        return (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findMostRecent(): ?NotFoundLog
    {
        return $this->createQueryBuilder('n')->orderBy('n.createdAt', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<NotFoundLog>
     */
    public function findRowsAfterIdUpTo(int $lastId, DateTimeImmutable $cutoff, int $limit): array
    {
        return array_values(
            $this
                ->createQueryBuilder('n')
                ->where('n.id > :lastId')
                ->andWhere('n.createdAt <= :cutoff')
                ->orderBy('n.id', 'ASC')
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
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.id > :lastId')
            ->andWhere('n.createdAt <= :cutoff')
            ->setParameter('lastId', $lastId)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFirstCreatedAtForIpAfter(string $ip, DateTimeImmutable $after): ?DateTimeImmutable
    {
        $row = $this
            ->createQueryBuilder('n')
            ->select('n.createdAt AS createdAt')
            ->where('n.ip = :ip')
            ->andWhere('n.createdAt > :after')
            ->orderBy('n.createdAt', 'ASC')
            ->setParameter('ip', $ip)
            ->setParameter('after', $after)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($row === null) {
            return null;
        }
        $value = $row['createdAt'];

        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
    }

    public function findLatestUnlinkedForOffender(string $ip, ?string $sessionId): ?NotFoundLog
    {
        return $this
            ->createQueryBuilder('n')
            ->where('n.ip = :ip')
            ->andWhere('n.incident IS NULL')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('ip', $ip)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<NotFoundLog>
     */
    public function findFiltered(
        int $limit,
        ?DateTimeImmutable $since,
        ?string $ip = null,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('n')->orderBy('n.createdAt', 'DESC')->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('n.createdAt >= :since')->setParameter('since', $since);
        }
        if ($ip !== null && $ip !== '') {
            $qb->andWhere('n.ip = :ip')->setParameter('ip', $ip);
        }
        if ($from !== null) {
            $qb->andWhere('n.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('n.createdAt <= :to')->setParameter('to', $to);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<array{bucket: string, number: int}>
     */
    private function getBucketCounts(string $format, int $limit, ?DateTimeImmutable $since): array
    {
        $qb = $this
            ->createQueryBuilder('n')
            ->select(sprintf('DATE_FORMAT(n.createdAt, \'%s\') AS bucket', $format), 'COUNT(n.id) AS number')
            ->groupBy('bucket')
            ->orderBy('bucket', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('n.createdAt >= :since')->setParameter('since', $since);
        }

        $rows = array_map(
            static fn(array $row): array => ['bucket' => (string) $row['bucket'], 'number' => (int) $row['number']],
            $qb->getQuery()->getArrayResult(),
        );

        return array_values(array_reverse($rows));
    }

    /**
     * @return list<array{value: string, number: int}>
     */
    private function getTopValues(string $field, int $limit, ?DateTimeImmutable $since, bool $skipEmpty): array
    {
        $qb = $this
            ->createQueryBuilder('n')
            ->select(sprintf('n.%s AS value', $field), 'COUNT(n.id) AS number')
            ->groupBy(sprintf('n.%s', $field))
            ->orderBy('number', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('n.createdAt >= :since')->setParameter('since', $since);
        }
        if ($skipEmpty) {
            $qb->andWhere(sprintf('n.%s IS NOT NULL', $field))->andWhere(sprintf("n.%s != ''", $field));
        }

        return array_values(array_map(
            static fn(array $row): array => ['value' => (string) $row['value'], 'number' => (int) $row['number']],
            $qb->getQuery()->getArrayResult(),
        ));
    }

    private function countDistinct(string $field, ?DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('n')->select(sprintf('COUNT(DISTINCT n.%s)', $field));

        if ($since !== null) {
            $qb->andWhere('n.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
