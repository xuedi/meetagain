<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SecurityMeasureLog;
use App\Enum\SecurityMeasure;
use App\Enum\SecurityMeasureOutcome;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityMeasureLog>
 */
class SecurityMeasureLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityMeasureLog::class);
    }

    public function incrementPassCounter(SecurityMeasure $measure, DateTimeImmutable $day, ?string $context, DateTimeImmutable $now): int
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->update()
            ->set('s.count', 's.count + 1')
            ->set('s.createdAt', ':now')
            ->where('s.day = :day')
            ->andWhere('s.measure = :measure')
            ->andWhere('s.outcome = :outcome')
            ->setParameter('now', $now)
            ->setParameter('day', $day)
            ->setParameter('measure', $measure->value)
            ->setParameter('outcome', SecurityMeasureOutcome::Passed->value);

        $qb->andWhere($context === null ? 's.context IS NULL' : 's.context = :context');
        if ($context !== null) {
            $qb->setParameter('context', $context);
        }

        return (int) $qb->getQuery()->execute();
    }

    public function countBlocks(?DateTimeImmutable $sinceDay = null): int
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.count), 0)')
            ->where('s.outcome = :outcome')
            ->setParameter('outcome', SecurityMeasureOutcome::Blocked->value);

        if ($sinceDay !== null) {
            $qb->andWhere('s.day >= :sinceDay')->setParameter('sinceDay', $sinceDay);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<string, array{checked: int, blocked: int, lastBlock: ?DateTimeImmutable}>
     */
    public function summaryByMeasure(?DateTimeImmutable $sinceDay = null): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->select('s.measure AS measure', 's.outcome AS outcome', 'SUM(s.count) AS total', 'MAX(s.createdAt) AS last')
            ->groupBy('s.measure', 's.outcome');

        if ($sinceDay !== null) {
            $qb->where('s.day >= :sinceDay')->setParameter('sinceDay', $sinceDay);
        }

        $summary = [];
        foreach (SecurityMeasure::cases() as $measure) {
            $summary[$measure->value] = ['checked' => 0, 'blocked' => 0, 'lastBlock' => null];
        }

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $key = $row['measure'] instanceof SecurityMeasure ? $row['measure']->value : (string) $row['measure'];
            if (!array_key_exists($key, $summary)) {
                continue;
            }

            $total = (int) $row['total'];
            $summary[$key]['checked'] += $total;

            $outcome = $row['outcome'] instanceof SecurityMeasureOutcome ? $row['outcome'] : SecurityMeasureOutcome::from((string) $row['outcome']);
            if ($outcome !== SecurityMeasureOutcome::Blocked) {
                continue;
            }

            $summary[$key]['blocked'] = $total;
            $summary[$key]['lastBlock'] = $row['last'] instanceof DateTimeImmutable ? $row['last'] : new DateTimeImmutable((string) $row['last']);
        }

        return $summary;
    }

    /**
     * @return array<string, array<string, int>> measure value => day (Y-m-d) => blocks
     */
    public function dailyBlockSeries(DateTimeImmutable $sinceDay): array
    {
        $rows = $this
            ->createQueryBuilder('s')
            ->select('s.measure AS measure', 's.day AS day', 'SUM(s.count) AS total')
            ->where('s.outcome = :outcome')
            ->andWhere('s.day >= :sinceDay')
            ->setParameter('outcome', SecurityMeasureOutcome::Blocked->value)
            ->setParameter('sinceDay', $sinceDay)
            ->groupBy('s.measure', 's.day')
            ->getQuery()
            ->getArrayResult();

        $series = [];
        foreach ($rows as $row) {
            $key = $row['measure'] instanceof SecurityMeasure ? $row['measure']->value : (string) $row['measure'];
            $day = $row['day'] instanceof DateTimeImmutable ? $row['day'] : new DateTimeImmutable((string) $row['day']);
            $series[$key][$day->format('Y-m-d')] = (int) $row['total'];
        }

        return $series;
    }

    /**
     * @return list<SecurityMeasureLog>
     */
    public function recentBlocks(int $limit, ?DateTimeImmutable $sinceDay = null): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->where('s.outcome = :outcome')
            ->setParameter('outcome', SecurityMeasureOutcome::Blocked->value)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($sinceDay !== null) {
            $qb->andWhere('s.day >= :sinceDay')->setParameter('sinceDay', $sinceDay);
        }

        return array_values($qb->getQuery()->getResult());
    }

    public function deleteOlderThan(DateTimeImmutable $cutoffDay): int
    {
        return (int) $this
            ->createQueryBuilder('s')
            ->delete()
            ->where('s.day < :cutoffDay')
            ->setParameter('cutoffDay', $cutoffDay)
            ->getQuery()
            ->execute();
    }
}
