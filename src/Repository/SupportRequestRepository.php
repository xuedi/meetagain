<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Enum\SupportRequestStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SensitiveParameter;
use SortDirection;

/** @extends ServiceEntityRepository<SupportRequest> */
class SupportRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportRequest::class);
    }

    public function consumeEmailVerifyToken(SupportRequest $request, #[SensitiveParameter] string $token, DateTimeImmutable $verifiedAt): bool
    {
        $claimed = $this
            ->createQueryBuilder('sr')
            ->update()
            ->set('sr.emailVerifyToken', 'NULL')
            ->set('sr.emailVerifyExpiresAt', 'NULL')
            ->set('sr.emailVerifiedAt', ':verifiedAt')
            ->where('sr.id = :id')
            ->andWhere('sr.emailVerifyToken = :token')
            ->setParameter('verifiedAt', $verifiedAt)
            ->setParameter('id', $request->getId())
            ->setParameter('token', $token)
            ->getQuery()
            ->execute();

        if ($claimed !== 1) {
            return false;
        }

        $request->setEmailVerifiedAt($verifiedAt);
        $request->setEmailVerifyToken(null);
        $request->setEmailVerifyExpiresAt(null);

        return true;
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
            ->orderBy('sr.createdAt', SortDirection::Descending)
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

    /**
     * @param array<int>|null $onlyIds null = unrestricted
     * @return SupportRequest[]
     */
    public function findOpen(?SupportAudience $audience, ?array $onlyIds): array
    {
        return $this
            ->scoped($audience, $onlyIds)
            ->andWhere('sr.status IN (:open)')
            ->setParameter('open', SupportRequestStatus::open())
            ->getQuery()
            ->getResult();
    }

    /** @return SupportRequest[] */
    public function findOthersFromSameIp(SupportRequest $request, int $limit): array
    {
        if ($request->getIpAddress() === null) {
            return [];
        }

        return $this
            ->createQueryBuilder('sr')
            ->where('sr.ipAddress = :ip')
            ->andWhere('sr.id != :id')
            ->setParameter('ip', $request->getIpAddress())
            ->setParameter('id', $request->getId())
            ->orderBy('sr.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return SupportRequest[] */
    public function findRecentForRequester(User $requester, int $limit): array
    {
        return $this
            ->createQueryBuilder('sr')
            ->where('sr.requester = :requester')
            ->setParameter('requester', $requester)
            ->orderBy('sr.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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

    public function anonymiseRequester(int $userId, string $email): int
    {
        $ids = array_map(
            intval(...),
            $this
                ->createQueryBuilder('sr')
                ->select('sr.id')
                ->where('IDENTITY(sr.requester) = :user OR LOWER(sr.email) = :email')
                ->setParameter('user', $userId)
                ->setParameter('email', mb_strtolower($email))
                ->getQuery()
                ->getSingleColumnResult(),
        );
        if ($ids === []) {
            return 0;
        }

        $em = $this->getEntityManager();
        $em->createQuery('UPDATE App\Entity\SupportMessage m SET m.ipAddress = NULL WHERE m.supportRequest IN (:ids)')->setParameter('ids', $ids)->execute();

        return (int) $em
            ->createQuery(
                'UPDATE App\Entity\SupportRequest sr SET sr.requester = NULL, sr.email = NULL, sr.ipAddress = NULL, sr.emailVerifyToken = NULL, sr.emailVerifyExpiresAt = NULL WHERE sr.id IN (:ids)',
            )
            ->setParameter('ids', $ids)
            ->execute();
    }
}
