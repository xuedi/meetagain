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
    public function findByEventWithUser(int $eventId): array
    {
        return $this
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->leftJoin('u.image', 'i')
            ->addSelect('i')
            ->where('c.event = :eventId')
            ->setParameter('eventId', $eventId)
            ->orderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int>|null $restrictToEventIds null = no restriction
     * @return array<Comment>
     */
    public function findRecentAcrossEvents(int $limit, ?array $restrictToEventIds = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->leftJoin('c.event', 'e')
            ->addSelect('e')
            ->orderBy('c.created_at', 'DESC')
            ->setMaxResults($limit);

        if ($restrictToEventIds !== null) {
            if ($restrictToEventIds === []) {
                return [];
            }
            $qb->andWhere('c.event IN (:eventIds)')
                ->setParameter('eventIds', $restrictToEventIds);
        }

        return $qb->getQuery()->getResult();
    }
}
