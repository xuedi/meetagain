<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incident;
use App\Enum\IncidentSeverity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Incident>
 */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    /**
     * @return list<Incident>
     */
    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.endedAt', 'DESC')->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('i.endedAt >= :since')->setParameter('since', $since);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<Incident>
     */
    public function getRecentBySeverity(int $limit, ?DateTimeImmutable $since, ?IncidentSeverity $minSeverity): array
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.endedAt', 'DESC')->setMaxResults($limit);

        if ($since !== null) {
            $qb->andWhere('i.endedAt >= :since')->setParameter('since', $since);
        }

        if ($minSeverity !== null) {
            $allowed = [];
            foreach (IncidentSeverity::cases() as $case) {
                if (self::severityRank($case) < self::severityRank($minSeverity)) {
                    continue;
                }

                $allowed[] = $case;
            }
            $qb->andWhere('i.severity IN (:severities)')->setParameter('severities', $allowed);
        }

        return array_values($qb->getQuery()->getResult());
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('i')->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
    }

    public function countSince(DateTimeImmutable $since): int
    {
        return (int) $this
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.endedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, DateTimeImmutable>
     */
    public function findLastEndedAtPerIp(): array
    {
        $rows = $this
            ->createQueryBuilder('i')
            ->select('i.ip AS ip', 'MAX(i.endedAt) AS lastEndedAt')
            ->groupBy('i.ip')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $value = $row['lastEndedAt'];
            $result[$row['ip']] = $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
        }

        return $result;
    }

    public function findOpenWindowForIp(string $ip, DateTimeImmutable $minEndedAt): ?Incident
    {
        $result = $this
            ->createQueryBuilder('i')
            ->where('i.ip = :ip')
            ->andWhere('i.endedAt >= :minEndedAt')
            ->setParameter('ip', $ip)
            ->setParameter('minEndedAt', $minEndedAt)
            ->orderBy('i.endedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Incident ? $result : null;
    }

    private static function severityRank(IncidentSeverity $severity): int
    {
        return match ($severity) {
            IncidentSeverity::Low => 0,
            IncidentSeverity::Medium => 1,
            IncidentSeverity::High => 2,
            IncidentSeverity::Critical => 3,
        };
    }
}
