<?php declare(strict_types=1);

namespace Plugin\Ranking\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Ranking\Entity\MemberRank;

/**
 * @extends ServiceEntityRepository<MemberRank>
 */
class MemberRankRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberRank::class);
    }

    public function save(MemberRank $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MemberRank $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findForUserAndGroup(int $userId, int $groupId): ?MemberRank
    {
        return $this->findOneBy(['userId' => $userId, 'groupId' => $groupId]);
    }

    /**
     * @return list<MemberRank>
     */
    public function findForLeaderboard(int $groupId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.groupId = :groupId')
            ->setParameter('groupId', $groupId)
            ->orderBy('m.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteAllForGroup(int $groupId): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.groupId = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->execute();
    }

    public function deleteAllForUser(int $userId): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
