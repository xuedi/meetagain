<?php

namespace Plugin\Dishes\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dishes\Entity\Dishes;

/**
 * @extends ServiceEntityRepository<Dishes>
 */
class DishesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dishes::class);
    }
}
