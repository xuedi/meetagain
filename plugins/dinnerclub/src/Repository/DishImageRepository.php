<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishImage;

class DishImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DishImage::class);
    }

    public function findByDish(Dish $dish): array
    {
        return $this->findBy(['dish' => $dish], ['sortOrder' => 'ASC', 'createdAt' => 'ASC']);
    }
}
