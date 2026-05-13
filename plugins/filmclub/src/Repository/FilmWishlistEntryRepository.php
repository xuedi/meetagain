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
}
