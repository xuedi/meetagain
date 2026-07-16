<?php declare(strict_types=1);

namespace Plugin\Dishes\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishLike;

/**
 * @extends ServiceEntityRepository<DishLike>
 */
class DishLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DishLike::class);
    }

    public function findByDishAndUser(Dish $dish, int $userId): ?DishLike
    {
        return $this->findOneBy(['dish' => $dish, 'userId' => $userId]);
    }

    /** @return list<int> */
    public function findDishIdsByUser(int $userId): array
    {
        $rows = $this
            ->createQueryBuilder('l')
            ->select('IDENTITY(l.dish) AS dish_id')
            ->where('l.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $r): int => (int) $r['dish_id'], $rows);
    }
}
