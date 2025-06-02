<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CmsBlock>
 */
class CmsBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsBlock::class);
    }

    public function getMaxPriority(): float
    {
        try {
            return $this->createQueryBuilder('b')
                ->select('MAX(b.priority)')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Throwable) {
            return 1;
        }
    }
}
