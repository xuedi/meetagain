<?php declare(strict_types=1);

namespace Plugin\Wishlist\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Wishlist\Entity\WishlistEntry;

/**
 * @extends ServiceEntityRepository<WishlistEntry>
 */
class WishlistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WishlistEntry::class);
    }

    /** @return WishlistEntry[] all of a user's wishes across every item type, highest priority first */
    public function findByUser(int $userId): array
    {
        return $this
            ->createQueryBuilder('w')
            ->where('w.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('w.priorityCounter', 'DESC')
            ->addOrderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndItem(int $userId, string $itemType, int $itemId): ?WishlistEntry
    {
        return $this->findOneBy(['userId' => $userId, 'itemType' => $itemType, 'itemId' => $itemId]);
    }

    /** Whether any wish exists at all. */
    public function hasAny(): bool
    {
        return $this->count([]) > 0;
    }

    /** @return WishlistEntry[] every wish, for the group view (user grouping happens in the service) */
    public function findAllForGroupView(): array
    {
        return $this
            ->createQueryBuilder('w')
            ->orderBy('w.userId', 'ASC')
            ->addOrderBy('w.priorityCounter', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Aggregated demand for one item type: item id, distinct wanters, summed priority.
     *
     * @param list<int>|null $allowedItemIds null: no restriction; []: block all
     *
     * @return array<array{item_id: int, wanter_count: int, total_priority: int}>
     */
    public function aggregateByItem(string $itemType, ?array $allowedItemIds = null): array
    {
        if ($allowedItemIds === []) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('w')
            ->select('w.itemId AS item_id')
            ->addSelect('COUNT(DISTINCT w.userId) AS wanter_count')
            ->addSelect('SUM(w.priorityCounter) AS total_priority')
            ->where('w.itemType = :itemType')
            ->setParameter('itemType', $itemType)
            ->groupBy('w.itemId')
            ->orderBy('wanter_count', 'DESC')
            ->addOrderBy('total_priority', 'DESC');

        if ($allowedItemIds !== null) {
            $qb->andWhere('w.itemId IN (:ids)')->setParameter('ids', $allowedItemIds);
        }

        $result = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $result[] = [
                'item_id' => (int) $row['item_id'],
                'wanter_count' => (int) $row['wanter_count'],
                'total_priority' => (int) $row['total_priority'],
            ];
        }

        return $result;
    }

    public function countWantersForItem(string $itemType, int $itemId): int
    {
        return (int) $this
            ->createQueryBuilder('w')
            ->select('COUNT(DISTINCT w.userId)')
            ->where('w.itemType = :itemType AND w.itemId = :itemId')
            ->setParameter('itemType', $itemType)
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param list<int>|null $allowedItemIds */
    public function incrementAllExceptWinner(string $itemType, int $winnerItemId, ?array $allowedItemIds = null): void
    {
        if ($allowedItemIds === []) {
            return;
        }

        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->update(WishlistEntry::class, 'w')
            ->set('w.priorityCounter', 'w.priorityCounter + 1')
            ->where('w.itemType = :itemType AND w.itemId != :winnerItemId')
            ->setParameter('itemType', $itemType)
            ->setParameter('winnerItemId', $winnerItemId);

        if ($allowedItemIds !== null) {
            $qb->andWhere('w.itemId IN (:ids)')->setParameter('ids', $allowedItemIds);
        }

        $qb->getQuery()->execute();
    }

    /** @param list<int>|null $allowedItemIds */
    public function deleteByItem(string $itemType, int $winnerItemId, ?array $allowedItemIds = null): void
    {
        if ($allowedItemIds === []) {
            return;
        }

        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->delete(WishlistEntry::class, 'w')
            ->where('w.itemType = :itemType AND w.itemId = :winnerItemId')
            ->setParameter('itemType', $itemType)
            ->setParameter('winnerItemId', $winnerItemId);

        if ($allowedItemIds !== null) {
            $qb->andWhere('w.itemId IN (:ids)')->setParameter('ids', $allowedItemIds);
        }

        $qb->getQuery()->execute();
    }
}
