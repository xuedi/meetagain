<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmWishlistEntry;

/**
 * @extends ServiceEntityRepository<FilmWishlistEntry>
 */
class FilmWishlistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmWishlistEntry::class);
    }

    public function save(FilmWishlistEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmWishlistEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return FilmWishlistEntry[] */
    public function findByUser(int $userId, ?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('w')
            ->where('w.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('w.priorityCounter', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('w.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByUserAndFilm(int $userId, int $filmId): ?FilmWishlistEntry
    {
        return $this->findOneBy(['userId' => $userId, 'film' => $filmId]);
    }

    /**
     * Returns aggregated data: film_id, wanter_count (distinct users), total_priority (sum of priorityCounter).
     * Used for the "By film" tab on the group wishlist view.
     *
     * @return array<array{film_id: int, wanter_count: int, total_priority: int}>
     */
    public function aggregateByFilm(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.film) AS film_id')
            ->addSelect('COUNT(DISTINCT w.userId) AS wanter_count')
            ->addSelect('SUM(w.priorityCounter) AS total_priority')
            ->groupBy('w.film')
            ->orderBy('wanter_count', 'DESC');

        if ($allowedIds !== null) {
            $qb->where('w.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** @return FilmWishlistEntry[] */
    public function findByFilmForIncrement(int $filmId): array
    {
        return $this->findBy(['film' => $filmId]);
    }

    /** @return FilmWishlistEntry[] */
    public function findAllForGroupView(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('w')
            ->innerJoin('w.film', 'f')
            ->addSelect('f')
            ->orderBy('w.userId', 'ASC')
            ->addOrderBy('w.priorityCounter', 'DESC');

        if ($allowedIds !== null) {
            $qb->where('w.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function countWantersForFilm(int $filmId): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(DISTINCT w.userId)')
            ->where('w.film = :filmId')
            ->setParameter('filmId', $filmId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function incrementAllExceptWinner(int $winnerFilmId, ?array $allowedIds = null): void
    {
        if ($allowedIds === []) {
            return;
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(FilmWishlistEntry::class, 'w')
            ->set('w.priorityCounter', 'w.priorityCounter + 1')
            ->where('w.film != :winnerFilmId')
            ->setParameter('winnerFilmId', $winnerFilmId);

        if ($allowedIds !== null) {
            $qb->andWhere('w.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        $qb->getQuery()->execute();
    }

    public function deleteByFilmInGroup(int $filmId, ?array $allowedIds = null): void
    {
        if ($allowedIds === []) {
            return;
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->delete(FilmWishlistEntry::class, 'w')
            ->where('w.film = :filmId')
            ->setParameter('filmId', $filmId);

        if ($allowedIds !== null) {
            $qb->andWhere('w.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        $qb->getQuery()->execute();
    }
}
