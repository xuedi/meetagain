<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupportRequest;
use App\Entity\SupportRequestView;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupportRequestView> */
class SupportRequestViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportRequestView::class);
    }

    public function countForRequest(SupportRequest $request): int
    {
        return (int) $this
            ->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.supportRequest = :request')
            ->setParameter('request', $request)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastSeenAt(SupportRequest $request): ?DateTimeImmutable
    {
        $lastSeenAt = $this
            ->createQueryBuilder('v')
            ->select('MAX(v.seenAt)')
            ->where('v.supportRequest = :request')
            ->setParameter('request', $request)
            ->getQuery()
            ->getSingleScalarResult();

        return is_string($lastSeenAt) ? new DateTimeImmutable($lastSeenAt) : null;
    }

    /**
     * @param list<int> $ids
     * @return SupportRequestView[]
     */
    public function findWithRequest(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this
            ->createQueryBuilder('v')
            ->addSelect('r')
            ->join('v.supportRequest', 'r')
            ->where('v.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
