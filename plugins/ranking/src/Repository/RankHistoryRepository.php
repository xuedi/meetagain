<?php declare(strict_types=1);

namespace Plugin\Ranking\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Ranking\Entity\RankHistory;

/**
 * @extends ServiceEntityRepository<RankHistory>
 */
class RankHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankHistory::class);
    }

    public function save(RankHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function deleteAllForGroup(int $groupId): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->where('h.groupId = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->execute();
    }

    public function deleteAllForUser(int $userId): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->where('h.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
