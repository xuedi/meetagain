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

    /**
     * Create query builder for admin forms with optional filtering.
     * Used in form dropdowns to limit location choices based on admin context.
     *
     * @param array<int>|null $restrictToLocationIds Optional location ID filter
     */
    public function createQueryBuilderForAdmin(?array $restrictToLocationIds = null)
    {
        $qb = $this->createQueryBuilder('l');

        if ($restrictToLocationIds === []) {
            // Empty filter means no locations should be shown
            $qb->where('1 = 0');
        } elseif ($restrictToLocationIds !== null) {
            $qb->where('l.id IN (:locationIds)')->setParameter('locationIds', $restrictToLocationIds);
        }

        return $qb->orderBy('l.name', 'ASC');
    }
}
