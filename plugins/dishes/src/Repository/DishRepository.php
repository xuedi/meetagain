<?php declare(strict_types=1);

namespace Plugin\Dishes\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dishes\Entity\Dish;

/**
 * @extends ServiceEntityRepository<Dish>
 */
class DishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dish::class);
    }

    /**
     * @return Dish[]
     */
    public function findApproved(): array
    {
        return $this->findBy(['approved' => true], ['createdAt' => 'DESC']);
    }

    /**
     * @return Dish[]
     */
    public function findPending(): array
    {
        return $this->findBy(['approved' => false], ['createdAt' => 'DESC']);
    }

    /**
     * @return Dish[]
     */
    public function findWithSuggestions(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.suggestions IS NOT NULL')
            ->andWhere("d.suggestions != '[]'")
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return Dish[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->where('d.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
