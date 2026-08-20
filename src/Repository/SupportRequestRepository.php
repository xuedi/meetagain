<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportRequest;
use App\Enum\SupportAudience;
use App\Enum\SupportRequestStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupportRequest> */
class SupportRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportRequest::class);
    }

    /**
     * @param array<int>|null $onlyIds null = unrestricted
     * @return SupportRequest[]
     */
    public function findForAdminList(?SupportAudience $audience, ?array $onlyIds): array
    {
        return $this
            ->scoped($audience, $onlyIds)
            ->select('sr', 'r')
            ->leftJoin('sr.requester', 'r')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @param array<int>|null $onlyIds null = unrestricted */
    public function countNew(?SupportAudience $audience = null, ?array $onlyIds = null): int
    {
        return (int) $this
            ->scoped($audience, $onlyIds)
            ->select('COUNT(sr.id)')
            ->andWhere('sr.status = :status')
            ->setParameter('status', SupportRequestStatus::New)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return SupportRequest[] */
    public function findStaleUnresolved(DateTimeImmutable $before): array
    {
        return $this
            ->createQueryBuilder('sr')
            ->where('sr.status != :resolved')
            ->andWhere('COALESCE(sr.lastActivityAt, sr.createdAt) < :before')
            ->setParameter('resolved', SupportRequestStatus::Resolved)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    /** @param array<int>|null $onlyIds null = unrestricted */
    private function scoped(?SupportAudience $audience, ?array $onlyIds): QueryBuilder
    {
        $qb = $this->createQueryBuilder('sr');

        if ($audience !== null) {
            $qb->andWhere('sr.audience = :audience')->setParameter('audience', $audience);
        }

        if ($onlyIds !== null) {
            $qb->andWhere('sr.id IN (:onlyIds)')->setParameter('onlyIds', $onlyIds);
        }

        return $qb;
    }

    /** @return SupportRequest[] */
    public function findExpiredEmailVerifications(DateTimeImmutable $now): array
    {
        return $this
            ->createQueryBuilder('sr')
            ->where('sr.emailVerifyToken IS NOT NULL')
            ->andWhere('sr.emailVerifyExpiresAt IS NOT NULL')
            ->andWhere('sr.emailVerifyExpiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
