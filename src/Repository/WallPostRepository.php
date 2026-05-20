<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\WallPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WallPost>
 */
class WallPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WallPost::class);
    }

    /**
     * @param array<int>|null $allowedIds null = no scope filter
     * @return array<WallPost>
     */
    public function findRecent(int $limit, ?array $allowedIds = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int>|null $allowedIds
     * @return array<WallPost>
     */
    public function findPaginated(int $page, int $perPage, ?array $allowedIds = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int>|null $allowedIds
     */
    public function countAll(?array $allowedIds = null): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)');

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return 0;
            }
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
