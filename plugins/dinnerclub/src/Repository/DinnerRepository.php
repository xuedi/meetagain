<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\Dinner;

/**
 * @extends ServiceEntityRepository<Dinner>
 */
class DinnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dinner::class);
    }

    public function findByEventId(int $eventId): ?Dinner
    {
        return $this->findOneBy(['event' => $eventId]);
    }
}
