<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Internal\Entity\Suggestion;

/** @extends ServiceEntityRepository<Suggestion> */
class SuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suggestion::class);
    }

    /** @return list<Suggestion> */
    public function findPending(): array
    {
        return $this
            ->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', Status::Pending)
            ->orderBy('s.createdAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Suggestion> */
    public function findPendingByProposer(int $proposerId, string $targetType): array
    {
        return $this
            ->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.proposedBy = :proposer')
            ->andWhere('s.targetType = :targetType')
            ->setParameter('status', Status::Pending)
            ->setParameter('proposer', $proposerId)
            ->setParameter('targetType', $targetType)
            ->orderBy('s.createdAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
