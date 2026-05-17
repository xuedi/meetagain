<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\Film;

/**
 * @extends ServiceEntityRepository<Film>
 */
class FilmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Film::class);
    }

    public function save(Film $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Film $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return Film[] */
    public function findAll(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->orderBy('f.title', 'ASC');

        if ($allowedIds !== null) {
            $qb->where('f.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByExternalId(string $externalId, string $source): ?Film
    {
        return $this->findOneBy(['externalId' => $externalId, 'externalSource' => $source]);
    }
}
