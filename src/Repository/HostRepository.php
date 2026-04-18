<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Host;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Host>
 */
class HostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Host::class);
    }

    /**
     * @param array<int>|null $restrictToHostIds
     * @return Host[]
     */
    public function findAllForAdmin(?array $restrictToHostIds = null): array
    {
        if ($restrictToHostIds === []) {
            return [];
        }
        $qb = $this->createQueryBuilder('h');
        if ($restrictToHostIds !== null) {
            $qb->andWhere('h.id IN (:hostIds)')->setParameter('hostIds', $restrictToHostIds);
        }

        return $qb->orderBy('h.name', 'ASC')->getQuery()->getResult();
    }

    public function createQueryBuilderForAdmin(?array $restrictToHostIds = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('h');
        if ($restrictToHostIds === []) {
            $qb->where('1 = 0');
            return $qb->orderBy('h.name', 'ASC');
        }
        if ($restrictToHostIds !== null) {
            $qb->where('h.id IN (:hostIds)')->setParameter('hostIds', $restrictToHostIds);
        }

        return $qb->orderBy('h.name', 'ASC');
    }
}
