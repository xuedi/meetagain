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
    public function findRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
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
    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        return $this->findRecent($limit, $since);
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
