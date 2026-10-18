<?php declare(strict_types=1);

namespace Module\Task\Internal\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\Run;

/**
 * @extends ServiceEntityRepository<Run>
 */
class RunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Run::class);
    }

    public function record(Run $run): void
    {
        $em = $this->getEntityManager();
        $em->persist($run);
        $em->flush();
        $em->detach($run);
    }

    /**
     * @return array<string, Run>
     */
    public function findLatestPerIdentifier(): array
    {
        $latestIds = $this->createQueryBuilder('latest')->select('MAX(latest.id)')->groupBy('latest.identifier');

        $runs = $this->createQueryBuilder('r')->where(sprintf('r.id IN (%s)', $latestIds->getDQL()))->getQuery()->getResult();

        $byIdentifier = [];
        foreach ($runs as $run) {
            $byIdentifier[$run->getIdentifier()] = $run;
        }

        return $byIdentifier;
    }

    /**
     * @param list<Status>|null $statuses
     *
     * @return list<Run>
     */
    public function findFiltered(int $limit, ?DateTimeImmutable $since = null, ?string $identifier = null, ?array $statuses = null): array
    {
        return $this->filtered($since, $identifier, $statuses)->orderBy('r.id', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * @param list<Status>|null $statuses
     */
    public function countFiltered(?DateTimeImmutable $since = null, ?string $identifier = null, ?array $statuses = null): int
    {
        return (int) $this->filtered($since, $identifier, $statuses)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
    }

    public function deleteStartedBefore(DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('r')
            ->delete()
            ->where('r.startedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    /**
     * @param list<Status>|null $statuses
     */
    private function filtered(?DateTimeImmutable $since, ?string $identifier, ?array $statuses): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        if ($since !== null) {
            $qb->andWhere('r.startedAt >= :since')->setParameter('since', $since);
        }
        if ($identifier !== null) {
            $qb->andWhere('r.identifier = :identifier')->setParameter('identifier', $identifier);
        }
        if ($statuses !== null) {
            $qb->andWhere('r.status IN (:statuses)')->setParameter('statuses', array_map(static fn(Status $status): string => $status->value, $statuses));
        }

        return $qb;
    }
}
