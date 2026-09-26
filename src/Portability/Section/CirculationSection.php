<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Contract\PortableLedgerEntry;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Contract\RequestStatus;
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
        private CirculationInterface $circulation,
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
        return array_keys($this->scoped($scope)['handovers']);
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $shelf = $this->scoped($scope);
        ['copies' => $copies, 'requests' => $requests, 'handovers' => $handovers, 'ledger' => $ledger] = $shelf;

        if ($copies === [] && $requests === [] && $handovers === [] && $ledger === []) {
            return [];
        }

        $users = $this->usersById($shelf);

        return [
            'copies' => array_map(fn(PortableCopy $copy): array => $this->copyRow($copy, $scope, $users), array_values($copies)),
            'requests' => array_map(fn(PortableRequest $request): array => $this->requestRow($request, $copies, $users), array_values($requests)),
            'handovers' => array_map(fn(PortableHandover $handover): array => $this->handoverRow(
                $handover,
                $scope,
                $requests,
                $users,
            ), array_values($handovers)),
            'ledger' => array_map(fn(PortableLedgerEntry $entry): array => $this->ledgerRow($entry, $copies, $handovers, $users), $ledger),
        ];
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $copies = [];
        foreach ($this->rowsOf($rows, 'copies') as $row) {
            $copy = $this->importCopy($row, $context);
            if ($copy !== null) {
                $copies[$copy->ref] = $copy;
            }
        }

        $requests = [];
        foreach ($this->rowsOf($rows, 'requests') as $row) {
            $request = $this->importRequest($row, $copies, $context);
            if ($request !== null) {
                $requests[$request->ref] = $request;
            }
        }

        $handovers = [];
        foreach ($this->rowsOf($rows, 'handovers') as $row) {
            $handover = $this->importHandover($row, $copies, $requests, $context);
            if ($handover !== null) {
                $handovers[$handover->ref] = $handover;
            }
        }

        $ledger = [];
        foreach ($this->rowsOf($rows, 'ledger') as $row) {
            $entry = $this->importLedgerEntry($row, $copies, $context);
            if ($entry !== null) {
                $ledger[] = $entry;
            }
        }

        $handoverIds = $this->circulation->restore(new PortableShelf(array_values($copies), array_values($requests), array_values($handovers), $ledger));
        $context->mapIds(CirculationInterface::COMMENT_TARGET, $handoverIds);
    }

    /**
     * @return array{copies: array<int, PortableCopy>, requests: array<int, PortableRequest>, handovers: array<int, PortableHandover>, ledger: list<PortableLedgerEntry>}
     */
    private function scoped(Scope $scope): array
    {
        if ($scope->circulationContexts === []) {
            return ['copies' => [], 'requests' => [], 'handovers' => [], 'ledger' => []];
        }

        $shelf = $this->circulation->export(array_map(strval(...), array_keys($scope->circulationContexts)));

        $copies = [];
        foreach ($shelf->copies as $copy) {
            if ($this->inScope($scope, $copy->itemType, $copy->itemId)) {
                $copies[$copy->ref] = $copy;
            }
        }

        $requests = [];
        foreach ($shelf->requests as $request) {
            if ($this->inScope($scope, $request->itemType, $request->itemId) && $scope->grantsId($request->userId, DataCategory::Collections)) {
                $requests[$request->ref] = $request;
            }
        }

        $handovers = [];
        foreach ($shelf->handovers as $handover) {
            $copyExported = isset($copies[$handover->copyRef]);
            $receiverGranted = $scope->grantsId($handover->toUserId, DataCategory::Collections);
            $giverGranted = $handover->fromUserId === null || $scope->grantsId($handover->fromUserId, DataCategory::Collections);
            if ($copyExported && $receiverGranted && $giverGranted) {
                $handovers[$handover->ref] = $handover;
            }
        }

        $ledger = [];
        foreach ($shelf->ledger as $entry) {
            $namesDroppedMember = array_any(
                [$entry->fromUserId, $entry->toUserId, $entry->actorUserId],
                static fn(?int $userId): bool => $userId !== null && !$scope->grantsId($userId, DataCategory::Collections),
            );
            if ($this->inScope($scope, $entry->itemType, $entry->itemId) && !$namesDroppedMember) {
                $ledger[] = $entry;
            }
        }

        return ['copies' => $copies, 'requests' => $requests, 'handovers' => $handovers, 'ledger' => $ledger];
    }

    /**
     * @param array<int, User> $users
     * @return array<string, mixed>
     */
    private function copyRow(PortableCopy $copy, Scope $scope, array $users): array
    {
        return [
            'ref' => $copy->ref,
            'item_type' => $copy->itemType,
            'item_ref' => $copy->itemId,
            'label' => $copy->label,
            'donated_by_email' => $scope->grantsId($copy->donatedByUserId, DataCategory::Collections) ? $this->emailOf($copy->donatedByUserId, $users) : null,
            'donated_at' => $copy->donatedAt->format(DateTimeInterface::ATOM),
            'holder_email' => $copy->holderUserId === null ? null : $scope->creditEmail($users[$copy->holderUserId] ?? null, DataCategory::Collections),
            'held_since' => $copy->heldSince?->format(DateTimeInterface::ATOM),
            'status' => $copy->status->value,
            'finished_at' => $copy->finishedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, PortableCopy> $copies
     * @param array<int, User> $users
     * @return array<string, mixed>
     */
    private function requestRow(PortableRequest $request, array $copies, array $users): array
    {
        $offeredCopyRef = $request->offeredCopyRef;

        return [
            'ref' => $request->ref,
            'item_type' => $request->itemType,
            'item_ref' => $request->itemId,
            'email' => $this->emailOf($request->userId, $users),
            'requested_at' => $request->requestedAt->format(DateTimeInterface::ATOM),
            'status' => $request->status->value,
            'offered_copy_ref' => $offeredCopyRef !== null && isset($copies[$offeredCopyRef]) ? $offeredCopyRef : null,
            'offered_at' => $request->offeredAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, PortableRequest> $requests
     * @param array<int, User> $users
     * @return array<string, mixed>
     */
    private function handoverRow(PortableHandover $handover, Scope $scope, array $requests, array $users): array
    {
        $requestRef = $handover->requestRef;
        $cancelledBy = $handover->cancelledByUserId;

        return [
            'ref' => $handover->ref,
            'copy_ref' => $handover->copyRef,
            'from_email' => $this->emailOf($handover->fromUserId, $users),
            'to_email' => $this->emailOf($handover->toUserId, $users),
            'request_ref' => $requestRef !== null && isset($requests[$requestRef]) ? $requestRef : null,
            'opened_at' => $handover->openedAt->format(DateTimeInterface::ATOM),
            'from_confirmed_at' => $handover->fromConfirmedAt?->format(DateTimeInterface::ATOM),
            'to_confirmed_at' => $handover->toConfirmedAt?->format(DateTimeInterface::ATOM),
            'completed_at' => $handover->completedAt?->format(DateTimeInterface::ATOM),
            'cancelled_at' => $handover->cancelledAt?->format(DateTimeInterface::ATOM),
            'cancelled_by_email' => $scope->grantsId($cancelledBy, DataCategory::Collections) ? $this->emailOf($cancelledBy, $users) : null,
            'status' => $handover->status->value,
        ];
    }

    /**
     * @param array<int, PortableCopy> $copies
     * @param array<int, PortableHandover> $handovers
     * @param array<int, User> $users
     * @return array<string, mixed>
     */
    private function ledgerRow(PortableLedgerEntry $entry, array $copies, array $handovers, array $users): array
    {
        $copyRef = $entry->copyRef;
        $handoverRef = $entry->handoverRef;

        return [
            'entry_type' => $entry->type->value,
            'item_type' => $entry->itemType,
            'item_ref' => $entry->itemId,
            'occurred_at' => $entry->occurredAt->format(DateTimeInterface::ATOM),
            'copy_ref' => $copyRef !== null && isset($copies[$copyRef]) ? $copyRef : null,
            'from_email' => $this->emailOf($entry->fromUserId, $users),
            'to_email' => $this->emailOf($entry->toUserId, $users),
            'actor_email' => $this->emailOf($entry->actorUserId, $users),
            'payload' => $handoverRef !== null && isset($handovers[$handoverRef])
                ? [self::PAYLOAD_HANDOVER => $handoverRef] + $entry->payload
                : $entry->payload,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function importCopy(array $row, ImportContext $context): ?PortableCopy
    {
        $itemId = $this->itemId($row, self::KIND_COPIES, $context);
        if ($itemId === null) {
            return null;
        }

        $itemType = (string) ($row['item_type'] ?? '');
        $context->count(self::KIND_COPIES, Outcome::Created);

        return new PortableCopy(
            ref: (int) ($row['ref'] ?? 0),
            context: $this->circulation->contextFor($itemType),
            itemType: $itemType,
            itemId: $itemId,
            donatedAt: $this->date($row['donated_at'] ?? null) ?? new DateTimeImmutable(),
            status: CopyStatus::tryFrom((string) ($row['status'] ?? '')) ?? CopyStatus::Available,
            label: isset($row['label']) ? (string) $row['label'] : null,
            donatedByUserId: $this->userId($row['donated_by_email'] ?? null, $context),
            holderUserId: $this->userId($row['holder_email'] ?? null, $context),
            heldSince: $this->date($row['held_since'] ?? null),
            finishedAt: $this->date($row['finished_at'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     * @param array<int, PortableCopy> $copies
     */
    private function importRequest(array $row, array $copies, ImportContext $context): ?PortableRequest
    {
        $itemId = $this->itemId($row, self::KIND_REQUESTS, $context);
        if ($itemId === null) {
            return null;
        }

        $userId = $this->userId($row['email'] ?? null, $context);
        if ($userId === null) {
            $context->count(self::KIND_REQUESTS, Outcome::Dropped);

            return null;
        }

        $itemType = (string) ($row['item_type'] ?? '');
        $context->count(self::KIND_REQUESTS, Outcome::Created);

        return new PortableRequest(
            ref: (int) ($row['ref'] ?? 0),
            context: $this->circulation->contextFor($itemType),
            itemType: $itemType,
            itemId: $itemId,
            userId: $userId,
            requestedAt: $this->date($row['requested_at'] ?? null) ?? new DateTimeImmutable(),
            status: RequestStatus::tryFrom((string) ($row['status'] ?? '')) ?? RequestStatus::Waiting,
            offeredCopyRef: $this->knownRef($row['offered_copy_ref'] ?? null, $copies),
            offeredAt: $this->date($row['offered_at'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     * @param array<int, PortableCopy> $copies
     * @param array<int, PortableRequest> $requests
     */
    private function importHandover(array $row, array $copies, array $requests, ImportContext $context): ?PortableHandover
    {
        $copyRef = $this->knownRef($row['copy_ref'] ?? null, $copies);
        $receiverId = $this->userId($row['to_email'] ?? null, $context);
        $giverEmail = $row['from_email'] ?? null;
        $giverId = $this->userId($giverEmail, $context);

        $giverMissing = $giverEmail !== null && $giverId === null;
        if ($copyRef === null || $receiverId === null || $giverMissing) {
            $context->count(self::KIND_HANDOVERS, Outcome::Dropped);

            return null;
        }

        $context->count(self::KIND_HANDOVERS, Outcome::Created);

        return new PortableHandover(
            ref: (int) ($row['ref'] ?? 0),
            copyRef: $copyRef,
            toUserId: $receiverId,
            openedAt: $this->date($row['opened_at'] ?? null) ?? new DateTimeImmutable(),
            status: HandoverStatus::tryFrom((string) ($row['status'] ?? '')) ?? HandoverStatus::Open,
            fromUserId: $giverId,
            requestRef: $this->knownRef($row['request_ref'] ?? null, $requests),
            fromConfirmedAt: $this->date($row['from_confirmed_at'] ?? null),
            toConfirmedAt: $this->date($row['to_confirmed_at'] ?? null),
            completedAt: $this->date($row['completed_at'] ?? null),
            cancelledAt: $this->date($row['cancelled_at'] ?? null),
            cancelledByUserId: $this->userId($row['cancelled_by_email'] ?? null, $context),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     * @param array<int, PortableCopy> $copies
     */
    private function importLedgerEntry(array $row, array $copies, ImportContext $context): ?PortableLedgerEntry
    {
        $itemId = $this->itemId($row, self::KIND_LEDGER, $context);
        if ($itemId === null) {
            return null;
        }

        $entryType = LedgerEntryType::tryFrom((string) ($row['entry_type'] ?? ''));
        $archivedCopyRef = $row['copy_ref'] ?? null;
        $copyRef = $this->knownRef($archivedCopyRef, $copies);
        $emails = ['from' => $row['from_email'] ?? null, 'to' => $row['to_email'] ?? null, 'actor' => $row['actor_email'] ?? null];
        $userIds = array_map(fn(mixed $email): ?int => $this->userId($email, $context), $emails);

        $copyMissing = $archivedCopyRef !== null && $copyRef === null;
        $userMissing = array_any(array_keys($emails), static fn(string $role): bool => $emails[$role] !== null && $userIds[$role] === null);
        if ($entryType === null || $copyMissing || $userMissing) {
            $context->count(self::KIND_LEDGER, Outcome::Dropped);

            return null;
        }

        $payload = [];
        foreach (is_array($row['payload'] ?? null) ? $row['payload'] : [] as $key => $value) {
            $payload[(string) $key] = $value;
        }

        $handoverRef = $payload[self::PAYLOAD_HANDOVER] ?? null;
        unset($payload[self::PAYLOAD_HANDOVER]);

        $itemType = (string) ($row['item_type'] ?? '');
        $context->count(self::KIND_LEDGER, Outcome::Created);

        return new PortableLedgerEntry(
            type: $entryType,
            context: $this->circulation->contextFor($itemType),
            itemType: $itemType,
            itemId: $itemId,
            occurredAt: $this->date($row['occurred_at'] ?? null) ?? new DateTimeImmutable(),
            copyRef: $copyRef,
            fromUserId: $userIds['from'],
            toUserId: $userIds['to'],
            actorUserId: $userIds['actor'],
            handoverRef: is_int($handoverRef) || is_string($handoverRef) ? (int) $handoverRef : null,
            payload: $payload,
        );
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
     * @param array{copies: array<int, PortableCopy>, requests: array<int, PortableRequest>, handovers: array<int, PortableHandover>, ledger: list<PortableLedgerEntry>} $shelf
     * @return array<int, User>
     */
    private function usersById(array $shelf): array
    {
        $userIds = [];
        foreach ($shelf['copies'] as $copy) {
            $userIds[] = $copy->donatedByUserId;
            $userIds[] = $copy->holderUserId;
        }

        foreach ($shelf['requests'] as $request) {
            $userIds[] = $request->userId;
        }

        foreach ($shelf['handovers'] as $handover) {
            array_push($userIds, $handover->fromUserId, $handover->toUserId, $handover->cancelledByUserId);
        }

        foreach ($shelf['ledger'] as $entry) {
            array_push($userIds, $entry->fromUserId, $entry->toUserId, $entry->actorUserId);
        }

        $userIds = array_values(array_unique(array_filter($userIds, static fn(?int $userId): bool => $userId !== null)));
        if ($userIds === []) {
            return [];
        }

        $users = [];
        foreach ($this->em->getRepository(User::class)->findBy(['id' => $userIds]) as $user) {
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

    private function userId(mixed $email, ImportContext $context): ?int
    {
        return $context->resolveRef(User::class, $email)?->getId();
    }

    /**
     * @param array<int, object> $known
     */
    private function knownRef(mixed $ref, array $known): ?int
    {
        return (is_int($ref) || is_string($ref)) && isset($known[(int) $ref]) ? (int) $ref : null;
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
