<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginAttempt;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Get failed attempts count for a user in the given time period.
     */
    public function getFailedAttemptsCount(User $user, DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.user = :user')
            ->andWhere('la.successful = false')
            ->andWhere('la.attemptedAt > :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total failed attempts in the given time period.
     */
    public function getTotalFailedCount(DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.successful = false')
            ->andWhere('la.attemptedAt > :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get login attempts summary for dashboard.
     *
     * @return array{total: int, successful: int, failed: int}
     */
    public function getStats(DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('la')
            ->select(
                'COUNT(la.id) as total',
                'SUM(CASE WHEN la.successful = true THEN 1 ELSE 0 END) as successful',
                'SUM(CASE WHEN la.successful = false THEN 1 ELSE 0 END) as failed'
            )
            ->where('la.attemptedAt > :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'successful' => (int) $result['successful'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * Get recent failed attempts for review.
     *
     * @return LoginAttempt[]
     */
    public function getRecentFailed(int $limit = 10): array
    {
        return $this->createQueryBuilder('la')
            ->leftJoin('la.user', 'u')
            ->addSelect('u')
            ->where('la.successful = false')
            ->orderBy('la.attemptedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
