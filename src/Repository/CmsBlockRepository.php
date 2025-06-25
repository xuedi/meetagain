<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

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
        } catch (Throwable) {
            return 1;
        }
    }
}
