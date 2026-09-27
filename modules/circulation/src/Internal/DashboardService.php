<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Entity\User;
use App\Item\FilterService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Module\Circulation\Internal\Repository\RequestRepository;

final readonly class DashboardService
{
    public const int ACTIVITY_PAGE_SIZE = 50;
    public const int TOP_DONORS = 5;

    public function __construct(
        private CirculationService $circulation,
        private CopyRepository $copies,
        private RequestRepository $requests,
        private HandoverRepository $handovers,
        private LedgerEntryRepository $entries,
        private EntityManagerInterface $em,
        private FilterService $itemFilter,
    ) {}

    /**
     * @return list<Copy>
     */
    public function getShelf(string $itemType, ?CopyStatus $status = null): array
    {
        $copies = $this->copies->findShelf($this->circulation->getContext($itemType), $itemType, $this->itemFilter->getAllowedItemIds($itemType));

        if ($status === null) {
            return $copies;
        }

        return array_values(array_filter($copies, static fn(Copy $copy): bool => $copy->getStatus() === $status));
    }

    /**
     * @return list<array{itemId: int, queue: list<Request>, viewerPosition: int|null}>
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
     * @return list<Handover>
     */
    public function getOpenHandovers(string $itemType, User $viewer, bool $seesAll): array
    {
        if ($seesAll) {
            return $this->handovers->findOpenInContext($this->circulation->getContext($itemType), $itemType);
        }

        return $this->handovers->findOpenForUser($viewer);
    }

    /**
     * @return list<LedgerEntry>
     */
    public function getCompletedHandovers(string $itemType, int $limit): array
    {
        $completed = $this->entries->findOfType($this->circulation->getContext($itemType), LedgerEntryType::HandoverCompleted);

        return array_slice(array_reverse($completed), 0, $limit);
    }

    /**
     * @return array{entries: list<LedgerEntry>, total: int, page: int, pages: int}
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
     * @return array{holding: list<Copy>, donated: list<Copy>, waiting: list<array{itemId: int, position: int, queueLength: int}>, openHandovers: list<Handover>, received: int, given: int, donations: int, longestHeld: Copy|null}
     */
    public function getMemberSummary(string $itemType, User $viewer): array
    {
        $context = $this->circulation->getContext($itemType);
        $viewerId = (int) $viewer->getId();
        $shelf = $this->getShelf($itemType);

        $holding = array_values(array_filter($shelf, static fn(Copy $copy): bool => $copy->isHeldBy($viewer)));
        usort(
            $holding,
            static fn(Copy $a, Copy $b): int => ($a->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX),
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
            static fn(Handover $handover): bool => $handover->getCopy()->getContext() === $context && $handover->getCopy()->getItemType() === $itemType,
        ));

        $received = 0;
        $given = 0;
        foreach ($this->entries->findOfType($context, LedgerEntryType::HandoverCompleted) as $entry) {
            $received += $entry->getToUserId() === $viewerId ? 1 : 0;
            $given += $entry->getFromUserId() === $viewerId ? 1 : 0;
        }

        return [
            'holding' => $holding,
            'donated' => array_values(array_filter($shelf, static fn(Copy $copy): bool => $copy->getDonatedBy()?->getId() === $viewerId)),
            'waiting' => $waiting,
            'openHandovers' => $openHandovers,
            'received' => $received,
            'given' => $given,
            'donations' => $this->entries->countDonationsPerUser($context)[$viewerId] ?? 0,
            'longestHeld' => $holding[0] ?? null,
        ];
    }

    /**
     * @return array{copies: int, available: int, completedHandovers: int, mostTravelled: array{copy: Copy, moves: int}|null, medianHoldingDays: int|null, topDonors: list<array{user: User, count: int}>, longestHeld: list<Copy>}
     */
    public function getStats(string $itemType): array
    {
        $context = $this->circulation->getContext($itemType);
        $shelf = $this->getShelf($itemType);
        $circulating = array_values(array_filter($shelf, static fn(Copy $copy): bool => $copy->getStatus()->isCirculating()));

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
            $user = $this->em->find(User::class, $userId);
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
            static fn(Copy $copy): bool => $copy->getStatus() === CopyStatus::Held && $copy->getFinishedAt() === null,
        ));
        usort(
            $longestHeld,
            static fn(Copy $a, Copy $b): int => ($a->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->getHeldSince()?->getTimestamp() ?? PHP_INT_MAX),
        );

        return [
            'copies' => count($circulating),
            'available' => count(array_filter($circulating, static fn(Copy $copy): bool => $copy->getStatus() === CopyStatus::Available)),
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

            $isHandStart = $entry->getEntryType() === LedgerEntryType::Donated || $entry->getEntryType() === LedgerEntryType::HandoverCompleted;
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
