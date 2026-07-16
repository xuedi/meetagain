<?php declare(strict_types=1);

namespace Plugin\Voting\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Voting\Entity\Poll;
use Plugin\Voting\Entity\PollStatus;

/**
 * @extends ServiceEntityRepository<Poll>
 */
class PollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poll::class);
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return Poll[]
     */
    public function findActive(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')->where('p.status = :status')->setParameter('status', PollStatus::Active)->orderBy('p.endDate', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Poll[] */
    public function findExpiredActive(): array
    {
        return $this
            ->createQueryBuilder('p')
            ->where('p.status = :status AND p.endDate < :now')
            ->setParameter('status', PollStatus::Active)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** @param list<int>|null $allowedIds */
    public function findActiveForEvent(int $eventId, ?array $allowedIds = null): ?Poll
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this
            ->createQueryBuilder('p')
            ->where('p.event = :eventId AND p.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', PollStatus::Active)
            ->setMaxResults(1);

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param list<int>|null $allowedIds
     *
     * @return Poll[]
     */
    public function findClosed(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')->where('p.status = :status')->setParameter('status', PollStatus::Closed)->orderBy('p.closedAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }
}
