<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Topic>
 */
class TopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }

    /**
     * @param array<int>|null $allowedIds null = no scope filter
     * @return list<Topic>
     */
    public function findAllInScope(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('t')
            ->leftJoin('t.author', 'a')
            ->addSelect('a')
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('t.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @param array<int>|null $allowedIds null = no scope filter
     */
    public function countInScope(?array $allowedIds = null): int
    {
        if ($allowedIds === []) {
            return 0;
        }

        $qb = $this->createQueryBuilder('t')->select('COUNT(t.id)');

        if ($allowedIds !== null) {
            $qb->andWhere('t.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<int> */
    public function descendantIds(Topic $topic): array
    {
        $descendants = [];
        $frontier = [$topic->getId()];

        while ($frontier !== []) {
            $rows = $this
                ->createQueryBuilder('t')
                ->select('t.id')
                ->where('t.parent IN (:parents)')
                ->setParameter('parents', $frontier)
                ->getQuery()
                ->getScalarResult();

            $frontier = array_values(array_diff(array_map('intval', array_column($rows, 'id')), $descendants));
            $descendants = [...$descendants, ...$frontier];
        }

        return $descendants;
    }

    public function countChildren(Topic $topic): int
    {
        return (int) $this
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.parent = :parent')
            ->setParameter('parent', $topic)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
