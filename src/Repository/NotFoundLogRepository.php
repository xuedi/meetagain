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
        // TODO: remove custom stuff (doctrine.yaml::DoctrineExtensions\Query\Mysql\DateFormat) and find a upstream way
        //       also fill up dateRange in sql and return key value pair straight as array

        // $dbal = $this->getDoctrine()->getConnection();
        // $idsAndNames = $dbal->executeQuery('SELECT id, name FROM Categories')->fetchAll(\PDO::FETCH_KEY_PAIR);

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
        $qb = $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id) as number', 'n.url')
            ->groupBy('n.url')
            ->orderBy('number', 'DESC')
            ->setMaxResults(100);

        if ($since !== null) {
            $qb->where('n.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('n.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();
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
        return $this
            ->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<string>
     */
    public function findIpsWithRowsBetween(DateTimeImmutable $after, DateTimeImmutable $before): array
    {
        $rows = $this
            ->createQueryBuilder('n')
            ->select('DISTINCT n.ip AS ip')
            ->where('n.createdAt > :after')
            ->andWhere('n.createdAt <= :before')
            ->setParameter('after', $after)
            ->setParameter('before', $before)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn(array $row): string => (string) $row['ip'], $rows));
    }

    /**
     * @return list<NotFoundLog>
     */
    public function findRowsForIpBetween(string $ip, DateTimeImmutable $after, DateTimeImmutable $before): array
    {
        return array_values(
            $this
                ->createQueryBuilder('n')
                ->where('n.ip = :ip')
                ->andWhere('n.createdAt > :after')
                ->andWhere('n.createdAt <= :before')
                ->orderBy('n.createdAt', 'ASC')
                ->setParameter('ip', $ip)
                ->setParameter('after', $after)
                ->setParameter('before', $before)
                ->getQuery()
                ->getResult(),
        );
    }

    public function hasRowForIpAfter(string $ip, DateTimeImmutable $after): bool
    {
        $count = (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.ip = :ip')
            ->andWhere('n.createdAt > :after')
            ->setParameter('ip', $ip)
            ->setParameter('after', $after)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
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
        $qb = $this
            ->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

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
}
