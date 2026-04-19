<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\Dinner;
use Plugin\Dinnerclub\Entity\DinnerCourse;

/**
 * @extends ServiceEntityRepository<DinnerCourse>
 */
class DinnerCourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DinnerCourse::class);
    }

    public function getNextSortOrder(Dinner $dinner): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.sortOrder)')
            ->where('c.dinner = :dinner')
            ->setParameter('dinner', $dinner)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) ($result ?? -1)) + 1;
    }
}
