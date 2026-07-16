<?php declare(strict_types=1);

namespace Plugin\Wishlist\Service;

use App\Filter\Event\EventFilterService;
use App\Item\ItemFilterService;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Wishlist\Entity\WishlistEntry;
use Plugin\Wishlist\Repository\WishlistEntryRepository;

/**
 * The per-member item backlog. Reads and writes are scoped to the item ids the core
 * ItemFilterService allows for a type, so the same backlog narrows automatically once a
 * scoping filter is registered. Never references voting or any item plugin directly.
 */
readonly class WishlistService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WishlistEntryRepository $wishlistRepo,
        private ItemFilterService $itemFilter,
        private EventFilterService $eventFilter,
        private EventRepository $eventRepo,
    ) {}

    public function add(string $itemType, int $itemId, int $userId): WishlistEntry
    {
        $existing = $this->wishlistRepo->findByUserAndItem($userId, $itemType, $itemId);
        if ($existing !== null) {
            return $existing;
        }

        $entry = new WishlistEntry();
        $entry->setUserId($userId);
        $entry->setItemType($itemType);
        $entry->setItemId($itemId);
        $entry->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public function remove(string $itemType, int $itemId, int $userId): void
    {
        $entry = $this->wishlistRepo->findByUserAndItem($userId, $itemType, $itemId);
        if ($entry === null) {
            return;
        }

        $this->em->remove($entry);
        $this->em->flush();
    }

    public function isWishlisted(string $itemType, int $itemId, int $userId): bool
    {
        return $this->wishlistRepo->findByUserAndItem($userId, $itemType, $itemId) !== null;
    }

    /** @return WishlistEntry[] the user's in-scope wishes, highest priority first */
    public function listForUser(int $userId): array
    {
        return $this->scopeEntries($this->wishlistRepo->findByUser($userId));
    }

    /**
     * @return array<array{itemId: int, wanterCount: int, totalPriority: int}> demand for one type, most-wanted first
     */
    public function aggregateByItem(string $itemType): array
    {
        $rows = $this->wishlistRepo->aggregateByItem($itemType, $this->itemFilter->getAllowedItemIds($itemType));

        return array_map(static fn(array $row): array => [
            'itemId' => $row['item_id'],
            'wanterCount' => $row['wanter_count'],
            'totalPriority' => $row['total_priority'],
        ], $rows);
    }

    /** @return list<int> ranked candidate item ids for a type, most-wanted first */
    public function getCandidateItemIds(string $itemType): array
    {
        return array_map(static fn(array $row): int => $row['itemId'], $this->aggregateByItem($itemType));
    }

    /** @return array<int, WishlistEntry[]> userId => their in-scope wishes */
    public function groupByMember(): array
    {
        $grouped = [];
        foreach ($this->scopeEntries($this->wishlistRepo->findAllForGroupView()) as $entry) {
            $grouped[(int) $entry->getUserId()][] = $entry;
        }

        return $grouped;
    }

    public function countWanters(string $itemType, int $itemId): int
    {
        return $this->wishlistRepo->countWantersForItem($itemType, $itemId);
    }

    /**
     * Aging denominator: how many in-scope events have happened since a wish was added. The one
     * intentional core coupling - the backlog reaches into the event stream, scoped by the core
     * event filter, to show "waited N events".
     */
    public function countPastEventsInGroupSince(DateTimeImmutable $since): int
    {
        $allowedEventIds = $this->eventFilter->getEventIdFilter()->getEventIds();
        if ($allowedEventIds === []) {
            return 0;
        }

        $qb = $this->eventRepo
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.start >= :since')
            ->andWhere('e.start < :now')
            ->setParameter('since', $since)
            ->setParameter('now', new DateTimeImmutable());

        if ($allowedEventIds !== null) {
            $qb->andWhere('e.id IN (:ids)')->setParameter('ids', $allowedEventIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Ages the backlog after an item of a type is chosen (by a vote or a direct attach): the
     * winner is cleared from every member's backlog, every other wish of that type ages up one.
     */
    public function onSelectionOutcome(string $itemType, int $winnerItemId): void
    {
        $allowedItemIds = $this->itemFilter->getAllowedItemIds($itemType);

        $this->wishlistRepo->incrementAllExceptWinner($itemType, $winnerItemId, $allowedItemIds);
        $this->wishlistRepo->deleteByItem($itemType, $winnerItemId, $allowedItemIds);
    }

    /**
     * @param WishlistEntry[] $entries
     *
     * @return WishlistEntry[] entries whose item is in scope for its type
     */
    private function scopeEntries(array $entries): array
    {
        $allowedByType = [];

        return array_values(array_filter($entries, function (WishlistEntry $entry) use (&$allowedByType): bool {
            $type = (string) $entry->getItemType();
            if (!array_key_exists($type, $allowedByType)) {
                $allowedByType[$type] = $this->itemFilter->getAllowedItemIds($type);
            }
            $allowed = $allowedByType[$type];

            return $allowed === null || in_array((int) $entry->getItemId(), $allowed, true);
        }));
    }
}
