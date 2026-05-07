<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeveloperAppApplication;
use App\Entity\User;
use App\Enum\DeveloperAppStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeveloperAppApplication>
 */
class DeveloperAppApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeveloperAppApplication::class);
    }

    /**
     * @return list<DeveloperAppApplication>
     */
    public function findRecentByUser(User $user): array
    {
        return array_values($this
            ->createQueryBuilder('a')
            ->where('a.submittedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('a.submittedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    public function findOneByUserAndId(User $user, int $id): ?DeveloperAppApplication
    {
        return $this->findOneBy(['id' => $id, 'submittedBy' => $user]);
    }

    /**
     * @return list<DeveloperAppApplication>
     */
    public function findPending(): array
    {
        return $this->findByStatus(DeveloperAppStatus::Pending, 200, 0);
    }

    /**
     * @return list<DeveloperAppApplication>
     */
    public function findByStatus(DeveloperAppStatus $status, int $limit = 200, int $offset = 0): array
    {
        return array_values($this
            ->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.submittedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<DeveloperAppApplication>
     */
    public function findRecent(int $limit = 200, int $offset = 0): array
    {
        return array_values($this
            ->createQueryBuilder('a')
            ->orderBy('a.submittedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult());
    }

    public function countByStatus(DeveloperAppStatus $status): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnreadOutcomeByUser(User $user): int
    {
        return (int) $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.submittedBy = :user')
            ->andWhere('a.userReadOutcome = false')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                DeveloperAppStatus::Approved,
                DeveloperAppStatus::Denied,
                DeveloperAppStatus::Revoked,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<DeveloperAppApplication>
     */
    public function findUnreadOutcomeByUser(User $user): array
    {
        return array_values($this
            ->createQueryBuilder('a')
            ->where('a.submittedBy = :user')
            ->andWhere('a.userReadOutcome = false')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                DeveloperAppStatus::Approved,
                DeveloperAppStatus::Denied,
                DeveloperAppStatus::Revoked,
            ])
            ->orderBy('a.reviewedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    public function findOneByClientIdentifier(string $identifier): ?DeveloperAppApplication
    {
        return $this->findOneBy(['clientIdentifier' => $identifier]);
    }
}
