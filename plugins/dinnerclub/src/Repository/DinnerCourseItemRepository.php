<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Dinnerclub\Entity\DinnerCourse;
use Plugin\Dinnerclub\Entity\DinnerCourseItem;

/**
 * @extends ServiceEntityRepository<DinnerCourseItem>
 */
class DinnerCourseItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DinnerCourseItem::class);
    }

    public function getNextSortOrder(DinnerCourse $course): int
    {
        $result = $this->createQueryBuilder('i')
            ->select('MAX(i.sortOrder)')
            ->where('i.course = :course')
            ->setParameter('course', $course)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) ($result ?? -1)) + 1;
    }
}
