<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishLike;

/** @extends ServiceEntityRepository<DishLike> */
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
        $likes = $this->findBy(['userId' => $userId]);
        return array_values(array_map(static fn(DishLike $like) => (int) $like->getDish()->getId(), $likes));
    }
}
