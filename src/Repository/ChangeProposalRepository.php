<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChangeProposal;
use App\Enum\ChangeProposalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ChangeProposal> */
class ChangeProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeProposal::class);
    }

    /** @return ChangeProposal[] */
    public function findPending(): array
    {
        return $this
            ->createQueryBuilder('cp')
            ->where('cp.status = :status')
            ->setParameter('status', ChangeProposalStatus::Pending)
            ->orderBy('cp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ChangeProposal[] */
    public function findPendingForTarget(string $targetType, int $targetId): array
    {
        return $this
            ->createQueryBuilder('cp')
            ->where('cp.status = :status')
            ->andWhere('cp.targetType = :targetType')
            ->andWhere('cp.targetId = :targetId')
            ->setParameter('status', ChangeProposalStatus::Pending)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->orderBy('cp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<int> */
    public function pendingTargetIds(string $targetType): array
    {
        $rows = $this
            ->createQueryBuilder('cp')
            ->select('DISTINCT cp.targetId')
            ->where('cp.status = :status')
            ->andWhere('cp.targetType = :targetType')
            ->setParameter('status', ChangeProposalStatus::Pending)
            ->setParameter('targetType', $targetType)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(intval(...), $rows);
    }

    public function countPendingForTarget(string $targetType, int $targetId): int
    {
        return (int) $this
            ->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->where('cp.status = :status')
            ->andWhere('cp.targetType = :targetType')
            ->andWhere('cp.targetId = :targetId')
            ->setParameter('status', ChangeProposalStatus::Pending)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function removeForTarget(string $targetType, int $targetId): void
    {
        $this
            ->createQueryBuilder('cp')
            ->delete()
            ->where('cp.targetType = :targetType')
            ->andWhere('cp.targetId = :targetId')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->getQuery()
            ->execute();
    }
}
