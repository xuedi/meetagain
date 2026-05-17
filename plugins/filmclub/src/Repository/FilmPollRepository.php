<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Entity\PollStatus;

/**
 * @extends ServiceEntityRepository<FilmPoll>
 */
class FilmPollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmPoll::class);
    }

    public function save(FilmPoll $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmPoll $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return FilmPoll[] */
    public function findActive(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PollStatus::Active)
            ->orderBy('p.endDate', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return FilmPoll[] */
    public function findExpiredActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status AND p.endDate < :now')
            ->setParameter('status', PollStatus::Active)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function findActiveForEvent(int $eventId, ?array $allowedIds = null): ?FilmPoll
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.event = :eventId AND p.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', PollStatus::Active)
            ->setMaxResults(1);

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return FilmPoll[] */
    public function findClosed(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PollStatus::Closed)
            ->orderBy('p.closedAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }
}
