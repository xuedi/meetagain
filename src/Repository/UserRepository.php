<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserStatus;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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

    // TODO: get via builder straight as keyValue
    public function getUserNameList(): array
    {
        if ($this->userNameList !== null) {
            return $this->userNameList;
        }
        $this->userNameList = [];
        foreach ($this->findAll() as $user) {
            $this->userNameList[$user->getId()] = $user->getName();
        }

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
    #[\Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
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
            ->leftJoin('u.image', 'i')  // Assuming 'image' is the property name
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
            ->leftJoin('u.image', 'i')  // Assuming 'image' is the property name
            ->where('u.status = :status')
            ->andwhere('u.id <> 1')
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
        return (int)$this->createQueryBuilder('u')
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
        return (int)$this->createQueryBuilder('u')
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
        return $qb->select('u')
            ->from(User::class, 'u')
            ->where($qb->expr()->isNotNull('u.regcode'))
            ->andWhere($qb->expr()->lt('u.createdAt', ':date'))
            ->andWhere($qb->expr()->eq('u.status', ':status'))
            ->setParameter('date', new DateTime('-'.$int.' days'))
            ->setParameter('status', UserStatus::Registered->value)
            ->getQuery()
            ->getResult();
    }
}
