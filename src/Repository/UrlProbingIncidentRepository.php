<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\UrlProbingIncident;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UrlProbingIncident>
 */
class UrlProbingIncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrlProbingIncident::class);
    }

    /**
     * @return list<UrlProbingIncident>
     */
    public function getRecent(int $limit = 200, ?DateTimeImmutable $since = null): array
    {
        $qb = $this
            ->createQueryBuilder('i')
            ->orderBy('i.endedAt', 'DESC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('i.endedAt >= :since')->setParameter('since', $since);
        }

        return array_values($qb->getQuery()->getResult());
    }

    public function countAll(): int
    {
        return (int) $this
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
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
            $result[$row['ip']] = $value instanceof DateTimeImmutable
                ? $value
                : new DateTimeImmutable((string) $value);
        }

        return $result;
    }
}
