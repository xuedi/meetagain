<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccountDeletionFeedback;
use App\Enum\AccountDeletionReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<AccountDeletionFeedback> */
class AccountDeletionFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountDeletionFeedback::class);
    }

    /** @return list<AccountDeletionFeedback> */
    public function findNewestFirst(): array
    {
        return $this
            ->createQueryBuilder('f')
            ->orderBy('f.createdAt', SortDirection::Descending)
            ->addOrderBy('f.id', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{reason: AccountDeletionReason, total: int}> */
    public function countByReason(): array
    {
        $rows = $this
            ->createQueryBuilder('f')
            ->select('f.reason AS reason', 'COUNT(f.id) AS total')
            ->groupBy('f.reason')
            ->orderBy('total', SortDirection::Descending)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): array => [
            'reason' => $row['reason'] instanceof AccountDeletionReason ? $row['reason'] : AccountDeletionReason::from((string) $row['reason']),
            'total' => (int) $row['total'],
        ], $rows);
    }
}
