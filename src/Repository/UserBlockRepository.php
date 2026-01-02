<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBlock>
 */
class UserBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBlock::class);
    }

    public function isBlocked(User $blocker, User $blocked): bool
    {
        return $this->findOneBy(['blocker' => $blocker, 'blocked' => $blocked]) !== null;
    }

    public function isBlockedEitherWay(User $user1, User $user2): bool
    {
        return $this->isBlocked($user1, $user2) || $this->isBlocked($user2, $user1);
    }

    /**
     * @return int[]
     */
    public function getBlockedUserIds(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->select('IDENTITY(b.blocked)')
            ->where('b.blocker = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return int[]
     */
    public function getBlockedByUserIds(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->select('IDENTITY(b.blocker)')
            ->where('b.blocked = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Get all user IDs that should be excluded from lists (blocked + blocked by).
     *
     * @return int[]
     */
    public function getAllBlockRelatedIds(User $user): array
    {
        $blocked = $this->getBlockedUserIds($user);
        $blockedBy = $this->getBlockedByUserIds($user);

        return array_unique(array_merge($blocked, $blockedBy));
    }

    /**
     * Get all users blocked by the given user (for blocked users list page).
     *
     * @return UserBlock[]
     */
    public function getBlockedUsers(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.blocked', 'u')
            ->addSelect('u')
            ->leftJoin('u.image', 'i')
            ->addSelect('i')
            ->where('b.blocker = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
