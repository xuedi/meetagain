<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CirculationLedgerEntry;
use App\Enum\CirculationLedgerEntryType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CirculationLedgerEntry>
 */
class CirculationLedgerEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CirculationLedgerEntry::class);
    }

    /**
     * @return list<CirculationLedgerEntry> in append order
     */
    public function findChronological(?string $context = null): array
    {
        $qb = $this->createQueryBuilder('l')->orderBy('l.id', 'ASC');
        if ($context !== null) {
            $qb->where('l.context = :context')->setParameter('context', $context);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<CirculationLedgerEntry> newest first
     */
    public function findTimeline(string $context, string $itemType, int $limit, int $offset, ?array $allowedItemIds = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.itemType = :itemType')->setParameter('itemType', $itemType)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($allowedItemIds !== null) {
            if ($allowedItemIds === []) {
                return [];
            }
            $qb->andWhere('l.itemId IN (:allowed)')->setParameter('allowed', $allowedItemIds);
        }

        return array_values($qb->getQuery()->getResult());
    }

    public function countTimeline(string $context, string $itemType, ?array $allowedItemIds = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.itemType = :itemType')->setParameter('itemType', $itemType);

        if ($allowedItemIds !== null) {
            if ($allowedItemIds === []) {
                return 0;
            }
            $qb->andWhere('l.itemId IN (:allowed)')->setParameter('allowed', $allowedItemIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<CirculationLedgerEntry>
     */
    public function findOfType(string $context, CirculationLedgerEntryType $entryType): array
    {
        return array_values($this->createQueryBuilder('l')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.entryType = :entryType')->setParameter('entryType', $entryType)
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult());
    }

    public function getMaxId(string $context): ?int
    {
        $max = $this->createQueryBuilder('l')
            ->select('MAX(l.id)')
            ->where('l.context = :context')->setParameter('context', $context)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? null : (int) $max;
    }

    public function countCompletedHandovers(string $context): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.entryType = :entryType')
            ->setParameter('entryType', CirculationLedgerEntryType::HandoverCompleted)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, int> completed-handover count keyed by copy id
     */
    public function countHandoversPerCopy(string $context): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.copyId AS copyId, COUNT(l.id) AS total')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.entryType = :entryType')
            ->setParameter('entryType', CirculationLedgerEntryType::HandoverCompleted)
            ->andWhere('l.copyId IS NOT NULL')
            ->groupBy('l.copyId')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['copyId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<int, int> donation count keyed by donor user id
     */
    public function countDonationsPerUser(string $context): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.actorUserId AS userId, COUNT(l.id) AS total')
            ->where('l.context = :context')->setParameter('context', $context)
            ->andWhere('l.entryType = :entryType')
            ->setParameter('entryType', CirculationLedgerEntryType::Donated)
            ->andWhere('l.actorUserId IS NOT NULL')
            ->groupBy('l.actorUserId')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['userId']] = (int) $row['total'];
        }

        return $counts;
    }
}
