<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Circulation\ContextResolver;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationLedgerEntry;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationHandoverStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class CirculationSection implements SectionInterface
{
    public const string KIND_COPIES = 'circulation_copies';
    public const string KIND_REQUESTS = 'circulation_requests';
    public const string KIND_HANDOVERS = 'circulation_handovers';
    public const string KIND_LEDGER = 'circulation_ledger';

    private const string PAYLOAD_HANDOVER = 'handoverId';

    public function __construct(
        private EntityManagerInterface $em,
        private ContextResolver $contextResolver,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'circulation';
    }

    #[Override]
    public function getOrder(): int
    {
        return 75;
    }

    /**
     * @return list<int>
     */
    public function exportedHandoverIds(Scope $scope): array
    {
        return array_keys($this->handovers($scope, $this->copies($scope)));
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $copies = $this->copies($scope);
        $requests = $this->requests($scope);
        $handovers = $this->handovers($scope, $copies);
        $ledger = $this->ledger($scope, $copies, $handovers);

        if ($copies === [] && $requests === [] && $handovers === [] && $ledger === []) {
            return [];
        }

        return [
            'copies' => array_map(fn(CirculationCopy $copy): array => $this->copyRow($copy, $scope), array_values($copies)),
            'requests' => array_map(fn(CirculationRequest $request): array => $this->requestRow($request, $copies), array_values($requests)),
            'handovers' => array_map(fn(CirculationHandover $handover): array => $this->handoverRow($handover, $scope, $requests), array_values($handovers)),
            'ledger' => $ledger,
        ];
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($this->rowsOf($rows, 'copies') as $row) {
            $this->importCopy($row, $context);
        }

        foreach ($this->rowsOf($rows, 'requests') as $row) {
            $this->importRequest($row, $context);
        }

        foreach ($this->rowsOf($rows, 'handovers') as $row) {
            $this->importHandover($row, $context);
        }

        $this->em->flush();

        foreach ($this->rowsOf($rows, 'ledger') as $row) {
            $this->importLedgerEntry($row, $context);
        }
    }

    /**
     * @return array<int, CirculationCopy>
     */
    private function copies(Scope $scope): array
    {
        if ($scope->circulationContexts === []) {
            return [];
        }

        $copies = [];
        foreach ($this->em->getRepository(CirculationCopy::class)->findBy(['context' => array_keys($scope->circulationContexts)], ['id' => 'ASC']) as $copy) {
            if (!$this->inScope($scope, $copy->getItemType(), $copy->getItemId())) {
                continue;
            }

            $copies[(int) $copy->getId()] = $copy;
        }

        return $copies;
    }

    /**
     * @return array<int, CirculationRequest>
     */
    private function requests(Scope $scope): array
    {
        if ($scope->circulationContexts === []) {
            return [];
        }

        $requests = [];
        foreach ($this->em->getRepository(CirculationRequest::class)->findBy(['context' => array_keys($scope->circulationContexts)], ['id' => 'ASC']) as $request) {
            $isScopedItem = $this->inScope($scope, $request->getItemType(), $request->getItemId());
            if (!$isScopedItem || !$scope->grants($request->getUser(), DataCategory::Collections)) {
                continue;
            }

            $requests[(int) $request->getId()] = $request;
        }

        return $requests;
    }

    /**
     * @param array<int, CirculationCopy> $copies
     * @return array<int, CirculationHandover>
     */
    private function handovers(Scope $scope, array $copies): array
    {
        if ($copies === []) {
            return [];
        }

        $handovers = [];
        foreach ($this->em->getRepository(CirculationHandover::class)->findBy(['copy' => array_values($copies)], ['id' => 'ASC']) as $handover) {
            $giver = $handover->getFromUser();
            $copyExported = isset($copies[(int) $handover->getCopy()->getId()]);
            $receiverGranted = $scope->grants($handover->getToUser(), DataCategory::Collections);
            $giverGranted = $giver === null || $scope->grants($giver, DataCategory::Collections);
            if (!$copyExported || !$receiverGranted || !$giverGranted) {
                continue;
            }

            $handovers[(int) $handover->getId()] = $handover;
        }

        return $handovers;
    }

    /**
     * @param array<int, CirculationCopy> $copies
     * @param array<int, CirculationHandover> $handovers
     * @return list<array<string, mixed>>
     */
    private function ledger(Scope $scope, array $copies, array $handovers): array
    {
        if ($scope->circulationContexts === []) {
            return [];
        }

        $entries = array_values(array_filter(
            $this->em->getRepository(CirculationLedgerEntry::class)->findBy(['context' => array_keys($scope->circulationContexts)], ['id' => 'ASC']),
            fn(CirculationLedgerEntry $entry): bool => $this->inScope($scope, $entry->getItemType(), $entry->getItemId()),
        ));
        $users = $this->usersById($entries);

        $rows = [];
        foreach ($entries as $entry) {
            $userIds = [$entry->getFromUserId(), $entry->getToUserId(), $entry->getActorUserId()];
            $namesDroppedMember = array_any(
                $userIds,
                static fn(?int $userId): bool => $userId !== null && !$scope->grants($users[$userId] ?? null, DataCategory::Collections),
            );
            if ($namesDroppedMember) {
                continue;
            }

            $copyId = $entry->getCopyId();
            $rows[] = [
                'entry_type' => $entry->getEntryType()->value,
                'item_type' => $entry->getItemType(),
                'item_ref' => $entry->getItemId(),
                'occurred_at' => $entry->getOccurredAt()->format(DateTimeInterface::ATOM),
                'copy_ref' => $copyId !== null && isset($copies[$copyId]) ? $copyId : null,
                'from_email' => $this->emailOf($entry->getFromUserId(), $users),
                'to_email' => $this->emailOf($entry->getToUserId(), $users),
                'actor_email' => $this->emailOf($entry->getActorUserId(), $users),
                'payload' => $this->exportPayload($entry->getPayload(), $handovers),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function copyRow(CirculationCopy $copy, Scope $scope): array
    {
        $donor = $copy->getDonatedBy();
        $holder = $copy->getHolder();

        return [
            'ref' => $copy->getId(),
            'item_type' => $copy->getItemType(),
            'item_ref' => $copy->getItemId(),
            'label' => $copy->getLabel(),
            'donated_by_email' => $scope->grants($donor, DataCategory::Collections) ? $donor?->getEmail() : null,
            'donated_at' => $copy->getDonatedAt()->format(DateTimeInterface::ATOM),
            'holder_email' => $holder === null ? null : $scope->creditEmail($holder, DataCategory::Collections),
            'held_since' => $copy->getHeldSince()?->format(DateTimeInterface::ATOM),
            'status' => $copy->getStatus()->value,
            'finished_at' => $copy->getFinishedAt()?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, CirculationCopy> $copies
     * @return array<string, mixed>
     */
    private function requestRow(CirculationRequest $request, array $copies): array
    {
        $offeredCopyId = $request->getOfferedCopy()?->getId();

        return [
            'ref' => $request->getId(),
            'item_type' => $request->getItemType(),
            'item_ref' => $request->getItemId(),
            'email' => $request->getUser()->getEmail(),
            'requested_at' => $request->getRequestedAt()->format(DateTimeInterface::ATOM),
            'status' => $request->getStatus()->value,
            'offered_copy_ref' => $offeredCopyId !== null && isset($copies[$offeredCopyId]) ? $offeredCopyId : null,
            'offered_at' => $request->getOfferedAt()?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, CirculationRequest> $requests
     * @return array<string, mixed>
     */
    private function handoverRow(CirculationHandover $handover, Scope $scope, array $requests): array
    {
        $requestId = $handover->getRequest()?->getId();
        $cancelledBy = $handover->getCancelledBy();

        return [
            'ref' => $handover->getId(),
            'copy_ref' => $handover->getCopy()->getId(),
            'from_email' => $handover->getFromUser()?->getEmail(),
            'to_email' => $handover->getToUser()->getEmail(),
            'request_ref' => $requestId !== null && isset($requests[$requestId]) ? $requestId : null,
            'opened_at' => $handover->getOpenedAt()->format(DateTimeInterface::ATOM),
            'from_confirmed_at' => $handover->getFromConfirmedAt()?->format(DateTimeInterface::ATOM),
            'to_confirmed_at' => $handover->getToConfirmedAt()?->format(DateTimeInterface::ATOM),
            'completed_at' => $handover->getCompletedAt()?->format(DateTimeInterface::ATOM),
            'cancelled_at' => $handover->getCancelledAt()?->format(DateTimeInterface::ATOM),
            'cancelled_by_email' => $scope->grants($cancelledBy, DataCategory::Collections) ? $cancelledBy?->getEmail() : null,
            'status' => $handover->getStatus()->value,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importCopy(array $row, ImportContext $context): void
    {
        $itemId = $this->itemId($row, self::KIND_COPIES, $context);
        if ($itemId === null) {
            return;
        }

        $itemType = (string) ($row['item_type'] ?? '');
        $copy = new CirculationCopy($this->contextResolver->resolve($itemType), $itemType, $itemId, $this->date($row['donated_at'] ?? null) ?? new DateTimeImmutable());
        $copy->setLabel(isset($row['label']) ? (string) $row['label'] : null);
        $copy->setDonatedBy($context->resolveRef(User::class, $row['donated_by_email'] ?? null));
        $copy->setHolder($context->resolveRef(User::class, $row['holder_email'] ?? null));
        $copy->setHeldSince($this->date($row['held_since'] ?? null));
        $copy->setStatus(CirculationCopyStatus::tryFrom((string) ($row['status'] ?? '')) ?? CirculationCopyStatus::Available);
        $copy->setFinishedAt($this->date($row['finished_at'] ?? null));

        $this->em->persist($copy);
        $context->mapRef(CirculationCopy::class, (int) ($row['ref'] ?? 0), $copy);
        $context->count(self::KIND_COPIES, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importRequest(array $row, ImportContext $context): void
    {
        $itemId = $this->itemId($row, self::KIND_REQUESTS, $context);
        if ($itemId === null) {
            return;
        }

        $user = $context->resolveRef(User::class, $row['email'] ?? null);
        if ($user === null) {
            $context->count(self::KIND_REQUESTS, Outcome::Dropped);

            return;
        }

        $itemType = (string) ($row['item_type'] ?? '');
        $request = new CirculationRequest($this->contextResolver->resolve($itemType), $itemType, $itemId, $user, $this->date($row['requested_at'] ?? null) ?? new DateTimeImmutable());
        $request->setStatus(CirculationRequestStatus::tryFrom((string) ($row['status'] ?? '')) ?? CirculationRequestStatus::Waiting);
        $request->setOfferedCopy($context->resolveRef(CirculationCopy::class, $row['offered_copy_ref'] ?? null));
        $request->setOfferedAt($this->date($row['offered_at'] ?? null));

        $this->em->persist($request);
        $context->mapRef(CirculationRequest::class, (int) ($row['ref'] ?? 0), $request);
        $context->count(self::KIND_REQUESTS, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importHandover(array $row, ImportContext $context): void
    {
        $copy = $context->resolveRef(CirculationCopy::class, $row['copy_ref'] ?? null);
        $receiver = $context->resolveRef(User::class, $row['to_email'] ?? null);
        $giverEmail = $row['from_email'] ?? null;
        $giver = $context->resolveRef(User::class, $giverEmail);

        $giverMissing = $giverEmail !== null && $giver === null;
        if ($copy === null || $receiver === null || $giverMissing) {
            $context->count(self::KIND_HANDOVERS, Outcome::Dropped);

            return;
        }

        $handover = new CirculationHandover($copy, $giver, $receiver, $this->date($row['opened_at'] ?? null) ?? new DateTimeImmutable());
        $handover->setRequest($context->resolveRef(CirculationRequest::class, $row['request_ref'] ?? null));
        $handover->setFromConfirmedAt($this->date($row['from_confirmed_at'] ?? null));
        $handover->setToConfirmedAt($this->date($row['to_confirmed_at'] ?? null));
        $handover->setCompletedAt($this->date($row['completed_at'] ?? null));
        $handover->setCancelledAt($this->date($row['cancelled_at'] ?? null));
        $handover->setCancelledBy($context->resolveRef(User::class, $row['cancelled_by_email'] ?? null));
        $handover->setStatus(CirculationHandoverStatus::tryFrom((string) ($row['status'] ?? '')) ?? CirculationHandoverStatus::Open);

        $this->em->persist($handover);
        $context->mapRef(CirculationHandover::class, (int) ($row['ref'] ?? 0), $handover);
        $context->count(self::KIND_HANDOVERS, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importLedgerEntry(array $row, ImportContext $context): void
    {
        $itemId = $this->itemId($row, self::KIND_LEDGER, $context);
        if ($itemId === null) {
            return;
        }

        $entryType = CirculationLedgerEntryType::tryFrom((string) ($row['entry_type'] ?? ''));
        $copyRef = $row['copy_ref'] ?? null;
        $copyId = $context->resolveRef(CirculationCopy::class, $copyRef)?->getId();
        $emails = ['from' => $row['from_email'] ?? null, 'to' => $row['to_email'] ?? null, 'actor' => $row['actor_email'] ?? null];
        $userIds = array_map(static fn(mixed $email): ?int => $context->resolveRef(User::class, $email)?->getId(), $emails);

        $copyMissing = $copyRef !== null && $copyId === null;
        $userMissing = array_any(array_keys($emails), static fn(string $role): bool => $emails[$role] !== null && $userIds[$role] === null);
        if ($entryType === null || $copyMissing || $userMissing) {
            $context->count(self::KIND_LEDGER, Outcome::Dropped);

            return;
        }

        $itemType = (string) ($row['item_type'] ?? '');
        $this->em->persist(new CirculationLedgerEntry(
            $entryType,
            $this->contextResolver->resolve($itemType),
            $itemType,
            $itemId,
            $this->date($row['occurred_at'] ?? null) ?? new DateTimeImmutable(),
            $copyId,
            $userIds['from'],
            $userIds['to'],
            $userIds['actor'],
            $this->importPayload(is_array($row['payload'] ?? null) ? $row['payload'] : [], $context),
        ));
        $context->count(self::KIND_LEDGER, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function itemId(array $row, string $kind, ImportContext $context): ?int
    {
        $itemType = (string) ($row['item_type'] ?? '');
        if (!$context->knowsItemType($itemType)) {
            $context->count($kind, Outcome::Skipped);

            return null;
        }

        $itemId = $context->resolveItem($itemType, $row['item_ref'] ?? null);
        if ($itemId === null) {
            $context->count($kind, Outcome::Dropped);
        }

        return $itemId;
    }

    /**
     * @param list<CirculationLedgerEntry> $entries
     * @return array<int, User>
     */
    private function usersById(array $entries): array
    {
        $userIds = [];
        foreach ($entries as $entry) {
            foreach ([$entry->getFromUserId(), $entry->getToUserId(), $entry->getActorUserId()] as $userId) {
                if ($userId === null) {
                    continue;
                }

                $userIds[$userId] = $userId;
            }
        }

        if ($userIds === []) {
            return [];
        }

        $users = [];
        foreach ($this->em->getRepository(User::class)->findBy(['id' => array_values($userIds)]) as $user) {
            $users[(int) $user->getId()] = $user;
        }

        return $users;
    }

    /**
     * @param array<int, User> $users
     */
    private function emailOf(?int $userId, array $users): ?string
    {
        return $userId === null ? null : ($users[$userId] ?? null)?->getEmail();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, CirculationHandover> $handovers
     * @return array<string, mixed>
     */
    private function exportPayload(array $payload, array $handovers): array
    {
        $handoverId = $payload[self::PAYLOAD_HANDOVER] ?? null;
        if ($handoverId !== null && !isset($handovers[(int) $handoverId])) {
            unset($payload[self::PAYLOAD_HANDOVER]);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $archived
     * @return array<string, mixed>
     */
    private function importPayload(array $archived, ImportContext $context): array
    {
        $payload = [];
        foreach ($archived as $key => $value) {
            $payload[(string) $key] = $value;
        }

        if (array_key_exists(self::PAYLOAD_HANDOVER, $payload)) {
            $handoverId = $context->resolveRef(CirculationHandover::class, $payload[self::PAYLOAD_HANDOVER])?->getId();
            if ($handoverId === null) {
                unset($payload[self::PAYLOAD_HANDOVER]);
            } else {
                $payload[self::PAYLOAD_HANDOVER] = $handoverId;
            }
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array<array-key, mixed>>
     */
    private function rowsOf(array $rows, string $key): array
    {
        $block = $rows[$key] ?? null;

        return is_array($block) ? array_values(array_filter($block, is_array(...))) : [];
    }

    private function inScope(Scope $scope, string $itemType, int $itemId): bool
    {
        return in_array($itemId, $scope->itemIds[$itemType] ?? [], true);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
