<?php declare(strict_types=1);

namespace Plugin\Dishes\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dishes\Entity\DishList;

/**
 * @extends ServiceEntityRepository<DishList>
 */
class DishListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DishList::class);
    }

    /**
     * @return DishList[]
     */
    public function findByUser(int $userId): array
    {
        return $this->findBy(['createdBy' => $userId], ['name' => 'ASC']);
    }

    /**
     * @return DishList[]
     */
    public function findPublic(): array
    {
        return $this->findBy(['isPublic' => true], ['name' => 'ASC']);
    }

    /**
     * @return DishList[]
     */
    public function findPublicByOthers(int $currentUserId): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.isPublic = true')
            ->andWhere('dl.createdBy != :userId')
            ->setParameter('userId', $currentUserId)
            ->orderBy('dl.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
