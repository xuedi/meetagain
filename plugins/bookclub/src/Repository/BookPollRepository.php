<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\BookPoll;
use Plugin\Bookclub\Entity\PollStatus;

/**
 * @extends ServiceEntityRepository<BookPoll>
 */
class BookPollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookPoll::class);
    }

    /**
     * @param int[]|null $allowedEventIds null = no restriction
     */
    public function findActivePoll(?array $allowedEventIds = null): ?BookPoll
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PollStatus::Active)
            ->setMaxResults(1);

        if ($allowedEventIds !== null) {
            if ($allowedEventIds === []) {
                return null;
            }
            $qb->andWhere('p.eventId IN (:eventIds)')->setParameter('eventIds', $allowedEventIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int[]|null $allowedEventIds null = no restriction
     */
    public function findLatestClosed(?array $allowedEventIds = null): ?BookPoll
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PollStatus::Closed)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($allowedEventIds !== null) {
            if ($allowedEventIds === []) {
                return null;
            }
            $qb->andWhere('p.eventId IN (:eventIds)')->setParameter('eventIds', $allowedEventIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByEventId(int $eventId): ?BookPoll
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }

    /**
     * @param int[]|null $allowedEventIds null = no restriction
     * @return int[]
     */
    public function findUsedEventIds(?array $allowedEventIds = null): array
    {
        $qb = $this->createQueryBuilder('p')->select('p.eventId');

        if ($allowedEventIds !== null) {
            if ($allowedEventIds === []) {
                return [];
            }
            $qb->where('p.eventId IN (:eventIds)')->setParameter('eventIds', $allowedEventIds);
        }

        return array_column($qb->getQuery()->getArrayResult(), 'eventId');
    }

    /**
     * @param int[]|null $allowedEventIds null = no restriction
     * @return BookPoll[]
     */
    public function findAll(?array $allowedEventIds = null): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');

        if ($allowedEventIds !== null) {
            if ($allowedEventIds === []) {
                return [];
            }
            $qb->where('p.eventId IN (:eventIds)')->setParameter('eventIds', $allowedEventIds);
        }

        return $qb->getQuery()->getResult();
    }
}
