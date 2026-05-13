<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmNote;

/**
 * @extends ServiceEntityRepository<FilmNote>
 */
class FilmNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmNote::class);
    }

    public function save(FilmNote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmNote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return FilmNote[] */
    public function findRevealedForFilm(int $filmId, ?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('n')
            ->where('n.film = :filmId AND n.revealToGroup = true')
            ->setParameter('filmId', $filmId)
            ->orderBy('n.createdAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('n.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function findUserNoteForFilm(int $userId, int $filmId): ?FilmNote
    {
        return $this->findOneBy(['userId' => $userId, 'film' => $filmId]);
    }
}
