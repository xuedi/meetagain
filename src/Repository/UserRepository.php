<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    private ?array $userNameList = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Get user id => name mapping without loading full entities.
     *
     * @return array<int, string>
     */
    public function getUserNameList(): array
    {
        if ($this->userNameList !== null) {
            return $this->userNameList;
        }

        $result = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->getQuery()
            ->getArrayResult();

        $this->userNameList = array_column($result, 'name', 'id');

        return $this->userNameList;
    }

    public function getFollowers(User $user, bool $excludeFriends = false): array
    {
        if ($excludeFriends === false) {
            return $user->getFollowers()->toArray();
        }
        $friendLessList = [];
        $following = $user->getFollowing();
        foreach ($user->getFollowers() as $follower) {
            if (!$following->contains($follower)) {
                $friendLessList[] = $follower;
            }
        }

        return $friendLessList;
    }

    public function getFollowing(User $user, bool $excludeFriends = false): array
    {
        if ($excludeFriends === false) {
            return $user->getFollowing()->toArray();
        }
        $friendLessList = [];
        $followers = $user->getFollowers();
        foreach ($user->getFollowing() as $follow) {
            if (!$followers->contains($follow)) {
                $friendLessList[] = $follow;
            }
        }

        return $friendLessList;
    }

    public function getFriends(User $user): array
    {
        $friendList = [];
        $followers = $user->getFollowers();
        foreach ($user->getFollowing() as $follow) {
            if ($followers->contains($follow)) {
                $friendList[] = $follow;
            }
        }

        return $friendList;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!($user instanceof User)) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // TODO: merge with findActiveMembers via param
    public function findActivePublicMembers(int $limit = 500, int $offset = 0): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, i') // forces to fet all columns from user and image table
            ->leftJoin('u.image', 'i') // Assuming 'image' is the property name
            ->where('u.status = :status')
            ->andWhere('u.public = :public')
            ->setParameter('status', UserStatus::Active)
            ->setParameter('public', true)
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findActiveMembers(int $limit = 500, int $offset = 0): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, i') // forces to fet all columns from user and image table
            ->leftJoin('u.image', 'i') // Assuming 'image' is the property name
            ->where('u.status = :status')
            ->setParameter('status', UserStatus::Active)
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // TODO: merge with getNumberOfActiveMembers via param
    public function getNumberOfActivePublicMembers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->andWhere('u.public = :public')
            ->setParameter('status', UserStatus::Active)
            ->setParameter('public', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getNumberOfActiveMembers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->andwhere('u.id <> 1')
            ->setParameter('status', UserStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function resolveUserName(int $userId): string
    {
        return $this->getUserNameList()[$userId];
    }

    public function getOldRegistrations(int $int)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb
            ->select('u')
            ->from(User::class, 'u')
            ->where($qb->expr()->isNotNull('u.regcode'))
            ->andWhere($qb->expr()->lt('u.createdAt', ':date'))
            ->andWhere($qb->expr()->eq('u.status', ':status'))
            ->setParameter('date', new DateTime('-' . $int . ' days'))
            ->setParameter('status', UserStatus::Registered->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user name => id mapping for form choices without loading full entities.
     *
     * @return array<string, int>
     */
    public function getAllUserChoice(): array
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->getQuery()
            ->getArrayResult();

        return array_column($result, 'id', 'name');
    }

    /**
     * @return array<string, int> Count of users per status
     */
    public function getStatusBreakdown(): array
    {
        $result = $this->createQueryBuilder('u')
            ->select('u.status, COUNT(u.id) as cnt')
            ->groupBy('u.status')
            ->getQuery()
            ->getResult();

        $breakdown = [];
        foreach (UserStatus::cases() as $status) {
            $breakdown[$status->name] = 0;
        }
        foreach ($result as $row) {
            $status = $row['status'];
            $breakdown[$status->name] = (int) $row['cnt'];
        }

        return $breakdown;
    }

    public function getRecentlyActiveCount(int $days = 7): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.lastLogin > :date')
            ->setParameter('date', new DateTime('-' . $days . ' days'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users stuck in EmailVerified status (verified but not approved).
     */
    public function getUnverifiedCount(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', UserStatus::EmailVerified)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total social network connections (following relationships).
     *
     * @return array{total: int}
     */
    public function getSocialNetworkStats(DateTimeImmutable $weekStart): array
    {
        $em = $this->getEntityManager();

        $total = (int) $em->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM user_user')
            ->fetchOne();

        return [
            'total' => $total,
        ];
    }

    /**
     * Get social counts for a user without loading full collections.
     *
     * @return array{following: int, followers: int, rsvp: int}
     */
    public function getSocialCounts(User $user): array
    {
        $em = $this->getEntityManager();
        $userId = $user->getId();

        $followingCount = (int) $em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(User::class, 'u')
            ->innerJoin('u.following', 'f')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $followersCount = (int) $em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(User::class, 'u')
            ->innerJoin('u.followers', 'f')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $rsvpCount = (int) $em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(\App\Entity\Event::class, 'e')
            ->innerJoin('e.rsvp', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'following' => $followingCount,
            'followers' => $followersCount,
            'rsvp' => $rsvpCount,
        ];
    }

    /**
     * Find users who have enabled announcement notifications.
     * Note: The notificationSettings.announcements check is done in PHP
     * since it's stored as JSON.
     *
     * @return User[]
     */
    public function findAnnouncementSubscribers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->andWhere('u.notification = :notificationEnabled')
            ->setParameter('status', UserStatus::Active)
            ->setParameter('notificationEnabled', true)
            ->getQuery()
            ->getResult();
    }
}
