<?php declare(strict_types=1);

namespace App\Circulation;

use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationLedgerEntry;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Item\FilterService;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationLedgerEntryRepository;
use App\Repository\CirculationRequestRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

final readonly class DashboardService
{
    public const int ACTIVITY_PAGE_SIZE = 50;
    public const int TOP_DONORS = 5;

    public function __construct(
        private CirculationService $circulation,
        private CirculationCopyRepository $copies,
        private CirculationRequestRepository $requests,
        private CirculationHandoverRepository $handovers,
        private CirculationLedgerEntryRepository $entries,
        private UserRepository $users,
        private FilterService $itemFilter,
    ) {}

    /**
     * @return list<CirculationCopy>
     */
    public function getShelf(string $itemType, ?CirculationCopyStatus $status = null): array
    {
        $copies = $this->copies->findShelf($this->circulation->getContext($itemType), $itemType, $this->itemFilter->getAllowedItemIds($itemType));

        if ($status === null) {
            return $copies;
        }

        return array_values(array_filter($copies, static fn(CirculationCopy $copy): bool => $copy->getStatus() === $status));
    }

    /**
     * @return list<array{itemId: int, queue: list<CirculationRequest>, viewerPosition: int|null}>
     */
    public function getWaiting(string $itemType, ?User $viewer): array
    {
        $open = $this->requests->findOpenInContext($this->circulation->getContext($itemType), $itemType, $this->itemFilter->getAllowedItemIds($itemType));

        $byItem = [];
        foreach ($open as $request) {
            $byItem[$request->getItemId()][] = $request;
        }

        $rows = [];
        foreach ($byItem as $itemId => $queue) {
            $viewerPosition = null;
            foreach ($queue as $index => $request) {
                if (!($viewer !== null && $request->getUser()->getId() === $viewer->getId())) {
                    continue;
                }

                $viewerPosition = $index + 1;
            }
            $rows[] = ['itemId' => $itemId, 'queue' => $queue, 'viewerPosition' => $viewerPosition];
        }

        usort($rows, static function (array $a, array $b): int {
            $ownership = ($b['viewerPosition'] === null ? 0 : 1) <=> ($a['viewerPosition'] === null ? 0 : 1);

            return $ownership !== 0 ? $ownership : count($b['queue']) <=> count($a['queue']);
        });

        return $rows;
    }

    /**
     * @return list<CirculationHandover>
     */
    public function getOpenHandovers(string $itemType, User $viewer, bool $seesAll): array
    {
        if ($seesAll) {
            return $this->handovers->findOpenInContext($this->circulation->getContext($itemType), $itemType);
        }

        return $this->handovers->findOpenForUser($viewer);
    }

    /**
     * @return list<CirculationLedgerEntry>
     */
    public function getCompletedHandovers(string $itemType, int $limit): array
    {
        $completed = $this->entries->findOfType($this->circulation->getContext($itemType), CirculationLedgerEntryType::HandoverCompleted);

        return array_slice(array_reverse($completed), 0, $limit);
    }

    /**
     * @return array{entries: list<CirculationLedgerEntry>, total: int, page: int, pages: int}
     */
    public function getActivity(string $itemType, int $page): array
    {
        $context = $this->circulation->getContext($itemType);
        $allowed = $this->itemFilter->getAllowedItemIds($itemType);
        $total = $this->entries->countTimeline($context, $itemType, $allowed);
        $pages = max(1, (int) ceil($total / self::ACTIVITY_PAGE_SIZE));
        $page = max(1, min($page, $pages));

        return [
            'entries' => $this->entries->findTimeline($context, $itemType, self::ACTIVITY_PAGE_SIZE, ($page - 1) * self::ACTIVITY_PAGE_SIZE, $allowed),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{holding: list<CirculationCopy>, donated: list<CirculationCopy>, waiting: list<array{itemId: int, position: int, queueLength: int}>, openHandovers: list<CirculationHandover>, received: int, given: int, donations: int, longestHeld: CirculationCopy|null}
     */
    public function getMemberSummary(string $itemType, User $viewer): array
    {
        $context = $this->circulation->getContext($itemType);
        $viewerId = (int) $viewer->getId();
        $shelf = $this->getShelf($itemType);

        $holding = array_values(array_filter($shelf, static fn(CirculationCopy $copy): bool => $copy->isHeldBy($viewer)));
        usort(
            $holding,
            static fn(CirculationCopy $a, CirculationCopy $b): int => (
                ($a->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX)
            ),
        );

        $waiting = [];
        foreach ($this->getWaiting($itemType, $viewer) as $row) {
            if ($row['viewerPosition'] === null) {
                continue;
            }
            $waiting[] = ['itemId' => $row['itemId'], 'position' => $row['viewerPosition'], 'queueLength' => count($row['queue'])];
        }

        $openHandovers = array_values(array_filter(
            $this->handovers->findOpenForUser($viewer),
            static fn(CirculationHandover $handover): bool => $handover->getCopy()->getContext() === $context
                && $handover->getCopy()->getItemType() === $itemType,
        ));

        $received = 0;
        $given = 0;
        foreach ($this->entries->findOfType($context, CirculationLedgerEntryType::HandoverCompleted) as $entry) {
            $received += $entry->getToUserId() === $viewerId ? 1 : 0;
            $given += $entry->getFromUserId() === $viewerId ? 1 : 0;
        }

        return [
            'holding' => $holding,
            'donated' => array_values(array_filter(
                $shelf,
                static fn(CirculationCopy $copy): bool => $copy->getDonatedBy()?->getId() === $viewerId,
            )),
            'waiting' => $waiting,
            'openHandovers' => $openHandovers,
            'received' => $received,
            'given' => $given,
            'donations' => $this->entries->countDonationsPerUser($context)[$viewerId] ?? 0,
            'longestHeld' => $holding[0] ?? null,
        ];
    }

    /**
     * @return array{copies: int, available: int, completedHandovers: int, mostTravelled: array{copy: CirculationCopy, moves: int}|null, medianHoldingDays: int|null, topDonors: list<array{user: User, count: int}>, longestHeld: list<CirculationCopy>}
     */
    public function getStats(string $itemType): array
    {
        $context = $this->circulation->getContext($itemType);
        $shelf = $this->getShelf($itemType);
        $circulating = array_values(array_filter($shelf, static fn(CirculationCopy $copy): bool => $copy->getStatus()->isCirculating()));

        $moves = $this->entries->countHandoversPerCopy($context);
        $mostTravelled = null;
        foreach ($circulating as $copy) {
            $count = $moves[(int) $copy->getId()] ?? 0;
            if ($mostTravelled === null || $count > $mostTravelled['moves']) {
                $mostTravelled = ['copy' => $copy, 'moves' => $count];
            }
        }

        $topDonors = [];
        foreach ($this->entries->countDonationsPerUser($context) as $userId => $count) {
            $user = $this->users->find($userId);
            if ($user === null) {
                continue;
            }
            $topDonors[] = ['user' => $user, 'count' => $count];
            if (count($topDonors) >= self::TOP_DONORS) {
                break;
            }
        }

        $longestHeld = array_values(array_filter(
            $circulating,
            static fn(CirculationCopy $copy): bool => $copy->getStatus() === CirculationCopyStatus::Held && $copy->getFinishedAt() === null,
        ));
        usort(
            $longestHeld,
            static fn(CirculationCopy $a, CirculationCopy $b): int => (
                ($a->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX)
            ),
        );

        return [
            'copies' => count($circulating),
            'available' => count(array_filter($circulating, static fn(CirculationCopy $copy): bool => $copy->getStatus() === CirculationCopyStatus::Available)),
            'completedHandovers' => $this->entries->countCompletedHandovers($context),
            'mostTravelled' => $mostTravelled,
            'medianHoldingDays' => $this->medianHoldingDays($context),
            'topDonors' => $topDonors,
            'longestHeld' => array_slice($longestHeld, 0, self::TOP_DONORS),
        ];
    }

    private function medianHoldingDays(string $context): ?int
    {
        $startedAt = [];
        $spans = [];
        foreach ($this->entries->findChronological($context) as $entry) {
            $copyId = $entry->getCopyId();
            if ($copyId === null) {
                continue;
            }

            $isHandStart =
                $entry->getEntryType() === CirculationLedgerEntryType::Donated || $entry->getEntryType() === CirculationLedgerEntryType::HandoverCompleted;
            if (!$isHandStart) {
                continue;
            }

            $previous = $startedAt[$copyId] ?? null;
            if ($previous instanceof DateTimeImmutable) {
                $spans[] = $entry->getOccurredAt()->getTimestamp() - $previous->getTimestamp();
            }
            $startedAt[$copyId] = $entry->getOccurredAt();
        }

        if ($spans === []) {
            return null;
        }

        sort($spans);
        $middle = intdiv(count($spans), 2);
        $median = (count($spans) % 2) === 1 ? $spans[$middle] : intdiv($spans[$middle - 1] + $spans[$middle], 2);

        return (int) round($median / 86400);
    }
}
