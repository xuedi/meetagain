<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return array<Comment>
     */
    public function findForTarget(string $targetType, int $targetId): array
    {
        return $this
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->leftJoin('u.image', 'i')
            ->addSelect('i')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int>|null $restrictToTargetIds null = no restriction
     * @return array<Comment>
     */
    public function findRecentForTargetType(string $targetType, int $limit, ?array $restrictToTargetIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.targetType = :targetType')
            ->setParameter('targetType', $targetType)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($restrictToTargetIds !== null) {
            if ($restrictToTargetIds === []) {
                return [];
            }
            $qb->andWhere('c.targetId IN (:targetIds)')->setParameter('targetIds', $restrictToTargetIds);
        }

        return $qb->getQuery()->getResult();
    }
}
