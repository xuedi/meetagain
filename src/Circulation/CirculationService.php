<?php declare(strict_types=1);

namespace App\Circulation;

use App\Activity\ActivityService;
use App\Activity\Messages\DonatedCopy;
use App\Activity\Messages\RequestedItem;
use App\Entity\CirculationCopy;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Item\FilterService;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class CirculationService
{
    /** @var array<string, Summary> */
    private array $summaries = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContextResolver $contextResolver,
        private readonly ParticipationResolver $participationResolver,
        private readonly EligibilityResolver $eligibilityResolver,
        private readonly FilterService $itemFilter,
        private readonly CirculationCopyRepository $copies,
        private readonly CirculationRequestRepository $requests,
        private readonly QueueService $queue,
        private readonly LedgerService $ledger,
        private readonly ActivityService $activityService,
    ) {}

    public function isEnabled(string $itemType): bool
    {
        return $this->participationResolver->isEnabled($itemType);
    }

    public function getContext(string $itemType): string
    {
        return $this->contextResolver->resolve($itemType);
    }

    public function canRequest(string $itemType, int $itemId, User $user): EligibilityVerdict
    {
        return $this->eligibilityResolver->resolve($this->getContext($itemType), $itemType, $itemId, $user);
    }

    /**
     * @return list<CirculationCopy>
     */
    public function getCopies(string $itemType, int $itemId): array
    {
        if (!$this->isEnabled($itemType)) {
            return [];
        }

        return $this->copies->findForItem(
            $this->getContext($itemType),
            $itemType,
            $itemId,
            $this->itemFilter->getAllowedItemIds($itemType),
        );
    }

    /**
     * @return list<CirculationRequest>
     */
    public function getQueue(string $itemType, int $itemId): array
    {
        if (!$this->isEnabled($itemType) || !$this->isVisible($itemType, $itemId)) {
            return [];
        }

        return $this->queue->getQueue($this->getContext($itemType), $itemType, $itemId);
    }

    public function getSummary(string $itemType, int $itemId, ?User $viewer = null): ?Summary
    {
        if (!$this->isEnabled($itemType)) {
            return null;
        }

        $memoKey = $this->memoKey($itemType, $itemId, $viewer);
        if (isset($this->summaries[$memoKey])) {
            return $this->summaries[$memoKey];
        }

        if (!$this->isVisible($itemType, $itemId)) {
            return null;
        }

        $this->warmSummaries($itemType, [$itemId], $viewer);

        return $this->summaries[$memoKey] ?? null;
    }

    /**
     * @param list<int> $itemIds
     */
    public function warmSummaries(string $itemType, array $itemIds, ?User $viewer = null): void
    {
        if ($itemIds === [] || !$this->isEnabled($itemType)) {
            return;
        }

        $allowed = $this->itemFilter->getAllowedItemIds($itemType);
        if ($allowed !== null) {
            $itemIds = array_values(array_intersect($itemIds, $allowed));
        }
        if ($itemIds === []) {
            return;
        }

        $context = $this->getContext($itemType);
        $copies = $this->copies->findForItems($context, $itemType, $itemIds);
        $queueCounts = $this->requests->countOpenPerItem($context, $itemType, $itemIds);
        $ownRequests = $viewer === null
            ? []
            : $this->requests->findOpenForUserAndItems($context, $itemType, $itemIds, $viewer);

        $totals = [];
        $available = [];
        $viewerHolds = [];
        foreach ($copies as $copy) {
            $id = $copy->getItemId();
            $totals[$id] = ($totals[$id] ?? 0) + 1;
            if ($copy->getStatus() === CirculationCopyStatus::Available) {
                $available[$id] = ($available[$id] ?? 0) + 1;
            }
            if ($copy->isHeldBy($viewer)) {
                $viewerHolds[$id] = true;
            }
        }

        $ownByItem = [];
        foreach ($ownRequests as $request) {
            $ownByItem[$request->getItemId()] = $request;
        }

        foreach ($itemIds as $itemId) {
            $own = $ownByItem[$itemId] ?? null;
            $this->summaries[$this->memoKey($itemType, $itemId, $viewer)] = new Summary(
                $itemType,
                $itemId,
                $totals[$itemId] ?? 0,
                $available[$itemId] ?? 0,
                $queueCounts[$itemId] ?? 0,
                isset($viewerHolds[$itemId]),
                $own === null ? null : $this->queue->positionOf($own),
                $own !== null,
            );
        }
    }

    public function donate(string $itemType, int $itemId, User $donor, ?string $label): CirculationCopy
    {
        $this->assertEnabled($itemType);

        $now = new DateTimeImmutable();
        $context = $this->getContext($itemType);
        $copy = new CirculationCopy($context, $itemType, $itemId, $now);
        $copy->setLabel($this->normalizeLabel($label));
        $copy->setDonatedBy($donor);
        $copy->setHolder($donor);
        $copy->setHeldSince($now);
        $copy->setStatus(CirculationCopyStatus::Available);

        $this->em->persist($copy);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::Donated,
            $context,
            $itemType,
            $itemId,
            $now,
            $copy->getId(),
            null,
            $donor->getId(),
            $donor->getId(),
            ['label' => $copy->getLabel()],
        );

        $this->activityService->log(DonatedCopy::TYPE, $donor, ['item_type' => $itemType, 'item_id' => $itemId]);
        $this->queue->offerToNext($copy);

        return $copy;
    }

    public function request(string $itemType, int $itemId, User $user): CirculationRequest
    {
        $this->assertEnabled($itemType);
        $context = $this->getContext($itemType);

        $verdict = $this->eligibilityResolver->resolve($context, $itemType, $itemId, $user);
        if (!$verdict->allowed) {
            throw new RuntimeException($verdict->reasonKey ?? 'circulation.flash_not_eligible');
        }

        $existing = $this->requests->findOpenFor($context, $itemType, $itemId, $user);
        if ($existing !== null) {
            return $existing;
        }

        $now = new DateTimeImmutable();
        $request = new CirculationRequest($context, $itemType, $itemId, $user, $now);
        $this->em->persist($request);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::Requested,
            $context,
            $itemType,
            $itemId,
            $now,
            null,
            null,
            $user->getId(),
            $user->getId(),
        );

        $this->activityService->log(RequestedItem::TYPE, $user, ['item_type' => $itemType, 'item_id' => $itemId]);
        $this->offerAnyAvailableCopy($context, $itemType, $itemId);

        return $request;
    }

    public function cancelRequest(CirculationRequest $request, User $user): void
    {
        if (!$request->isOpen()) {
            return;
        }

        $copy = $request->getOfferedCopy();
        $request->setStatus(CirculationRequestStatus::Cancelled);
        $request->setOfferedCopy(null);
        $request->setOfferedAt(null);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::RequestCancelled,
            $request->getContext(),
            $request->getItemType(),
            $request->getItemId(),
            new DateTimeImmutable(),
            $copy?->getId(),
            null,
            $request->getUser()->getId(),
            $user->getId(),
        );
    }

    public function markFinished(CirculationCopy $copy, User $user): void
    {
        if ($copy->getStatus() !== CirculationCopyStatus::Held && $copy->getStatus() !== CirculationCopyStatus::Available) {
            throw new RuntimeException('circulation.flash_copy_busy');
        }

        $now = new DateTimeImmutable();
        $copy->setFinishedAt($now);
        $copy->setStatus(CirculationCopyStatus::Available);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::MarkedFinished,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            null,
            null,
            $user->getId(),
        );

        $this->queue->offerToNext($copy);
    }

    public function retire(CirculationCopy $copy, User $user, bool $lost = false): void
    {
        $now = new DateTimeImmutable();
        $copy->setStatus($lost ? CirculationCopyStatus::Lost : CirculationCopyStatus::Retired);
        $this->em->flush();

        $this->ledger->append(
            $lost ? CirculationLedgerEntryType::Lost : CirculationLedgerEntryType::Retired,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            null,
            null,
            $user->getId(),
        );
    }

    public function findOwnRequest(string $itemType, int $itemId, User $user): ?CirculationRequest
    {
        return $this->requests->findOpenFor($this->getContext($itemType), $itemType, $itemId, $user);
    }

    public function offerAnyAvailableCopy(string $context, string $itemType, int $itemId): void
    {
        foreach ($this->copies->findAvailableForItem($context, $itemType, $itemId) as $copy) {
            if ($this->queue->offerToNext($copy) === null) {
                return;
            }
        }
    }

    private function isVisible(string $itemType, int $itemId): bool
    {
        $allowed = $this->itemFilter->getAllowedItemIds($itemType);

        return $allowed === null || in_array($itemId, $allowed, true);
    }

    private function assertEnabled(string $itemType): void
    {
        if (!$this->isEnabled($itemType)) {
            throw new RuntimeException('circulation.flash_disabled');
        }
    }

    private function normalizeLabel(?string $label): ?string
    {
        $trimmed = trim((string) $label);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, CirculationCopy::MAX_LABEL_LENGTH);
    }

    private function memoKey(string $itemType, int $itemId, ?User $viewer): string
    {
        return $itemType . "\0" . $itemId . "\0" . ($viewer?->getId() ?? 0);
    }
}
