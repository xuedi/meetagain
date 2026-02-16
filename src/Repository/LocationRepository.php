<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * Find all locations for admin interface with optional filtering.
     *
     * @param array<int>|null $restrictToLocationIds Optional location ID filter
     * @return array<Location>
     */
    public function findAllForAdmin(?array $restrictToLocationIds = null): array
    {
        if ($restrictToLocationIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('l');

        if ($restrictToLocationIds !== null) {
            $qb->andWhere('l.id IN (:locationIds)')->setParameter('locationIds', $restrictToLocationIds);
        }

        return $qb->orderBy('l.name', 'ASC')->getQuery()->getResult();
    }
}
