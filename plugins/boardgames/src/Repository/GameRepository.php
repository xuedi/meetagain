<?php declare(strict_types=1);

namespace Plugin\Boardgames\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Boardgames\Entity\Game;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function save(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return list<Game>
     */
    public function findAllOrdered(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')->orderBy('g.name', 'ASC');

        if ($allowedIds !== null) {
            $qb->where('g.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     */
    public function findOneAllowed(int $id, ?array $allowedIds): ?Game
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('g')->andWhere('g.id = :id')->setParameter('id', $id);

        if ($allowedIds !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByExternalId(string $externalId, string $source): ?Game
    {
        return $this->findOneBy(['externalId' => $externalId, 'externalSource' => $source]);
    }

    public function findByNameAndYear(string $name, ?int $yearPublished): ?Game
    {
        return $this->findOneBy(['name' => $name, 'yearPublished' => $yearPublished]);
    }

    /**
     * @param list<int>|null $allowedIds null: no restriction; []: block all
     *
     * @return list<Game>
     */
    public function searchByName(string $term, ?array $allowedIds, int $limit = 25): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.name LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('g.name', 'ASC')
            ->setMaxResults($limit);

        if ($allowedIds !== null) {
            $qb->andWhere('g.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }
}
