<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\PortableCopy;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Contract\PortableLedgerEntry;
use Module\Circulation\Contract\PortableRequest;
use Module\Circulation\Contract\PortableShelf;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\LedgerEntry;
use Module\Circulation\Internal\Entity\Request;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Override;

final readonly class ShelfArchive implements CirculationInterface
{
    private const string PAYLOAD_HANDOVER = 'handoverId';

    public function __construct(
        private EntityManagerInterface $em,
        private ContextResolver $contextResolver,
        private LedgerEntryRepository $ledger,
    ) {}

    #[Override]
    public function contextFor(string $itemType): string
    {
        return $this->contextResolver->resolve($itemType);
    }

    #[Override]
    public function ledgerContexts(): array
    {
        return $this->ledger->findContextItemTypes();
    }

    #[Override]
    public function export(array $contexts): PortableShelf
    {
        if ($contexts === []) {
            return new PortableShelf();
        }

        $copies = $this->em->getRepository(Copy::class)->findBy(['context' => $contexts], ['id' => 'ASC']);
        $requests = $this->em->getRepository(Request::class)->findBy(['context' => $contexts], ['id' => 'ASC']);
        $handovers = $copies === [] ? [] : $this->em->getRepository(Handover::class)->findBy(['copy' => $copies], ['id' => 'ASC']);
        $ledger = $this->em->getRepository(LedgerEntry::class)->findBy(['context' => $contexts], ['id' => 'ASC']);

        return new PortableShelf(
            array_map($this->portableCopy(...), $copies),
            array_map($this->portableRequest(...), $requests),
            array_map($this->portableHandover(...), $handovers),
            array_map($this->portableLedgerEntry(...), $ledger),
        );
    }

    #[Override]
    public function restore(PortableShelf $shelf): array
    {
        $copies = [];
        foreach ($shelf->copies as $row) {
            $copy = new Copy($row->context, $row->itemType, $row->itemId, $row->donatedAt);
            $copy->setLabel($row->label);
            $copy->setDonatedBy($this->user($row->donatedByUserId));
            $copy->setHolder($this->user($row->holderUserId));
            $copy->setHeldSince($row->heldSince);
            $copy->setStatus($row->status);
            $copy->setFinishedAt($row->finishedAt);
            $this->em->persist($copy);
            $copies[$row->ref] = $copy;
        }

        $requests = [];
        foreach ($shelf->requests as $row) {
            $request = new Request($row->context, $row->itemType, $row->itemId, $this->em->getReference(User::class, $row->userId), $row->requestedAt);
            $request->setStatus($row->status);
            $request->setOfferedCopy($row->offeredCopyRef === null ? null : $copies[$row->offeredCopyRef] ?? null);
            $request->setOfferedAt($row->offeredAt);
            $this->em->persist($request);
            $requests[$row->ref] = $request;
        }

        $handovers = [];
        foreach ($shelf->handovers as $row) {
            $copy = $copies[$row->copyRef] ?? null;
            if ($copy === null) {
                continue;
            }

            $handover = new Handover($copy, $this->user($row->fromUserId), $this->em->getReference(User::class, $row->toUserId), $row->openedAt);
            $handover->setRequest($row->requestRef === null ? null : $requests[$row->requestRef] ?? null);
            $handover->setFromConfirmedAt($row->fromConfirmedAt);
            $handover->setToConfirmedAt($row->toConfirmedAt);
            $handover->setCompletedAt($row->completedAt);
            $handover->setCancelledAt($row->cancelledAt);
            $handover->setCancelledBy($this->user($row->cancelledByUserId));
            $handover->setStatus($row->status);
            $this->em->persist($handover);
            $handovers[$row->ref] = $handover;
        }

        $this->em->flush();

        $handoverIds = array_map(static fn(Handover $handover): int => (int) $handover->getId(), $handovers);
        foreach ($shelf->ledger as $row) {
            $handoverId = $row->handoverRef === null ? null : $handoverIds[$row->handoverRef] ?? null;
            $this->em->persist(
                new LedgerEntry(
                    $row->type,
                    $row->context,
                    $row->itemType,
                    $row->itemId,
                    $row->occurredAt,
                    $row->copyRef === null ? null : ($copies[$row->copyRef] ?? null)?->getId(),
                    $row->fromUserId,
                    $row->toUserId,
                    $row->actorUserId,
                    $handoverId === null ? $row->payload : [self::PAYLOAD_HANDOVER => $handoverId] + $row->payload,
                ),
            );
        }

        $this->em->flush();

        return $handoverIds;
    }

    private function portableCopy(Copy $copy): PortableCopy
    {
        return new PortableCopy(
            ref: (int) $copy->getId(),
            context: $copy->getContext(),
            itemType: $copy->getItemType(),
            itemId: $copy->getItemId(),
            donatedAt: $copy->getDonatedAt(),
            status: $copy->getStatus(),
            label: $copy->getLabel(),
            donatedByUserId: $copy->getDonatedBy()?->getId(),
            holderUserId: $copy->getHolder()?->getId(),
            heldSince: $copy->getHeldSince(),
            finishedAt: $copy->getFinishedAt(),
        );
    }

    private function portableRequest(Request $request): PortableRequest
    {
        return new PortableRequest(
            ref: (int) $request->getId(),
            context: $request->getContext(),
            itemType: $request->getItemType(),
            itemId: $request->getItemId(),
            userId: (int) $request->getUser()->getId(),
            requestedAt: $request->getRequestedAt(),
            status: $request->getStatus(),
            offeredCopyRef: $request->getOfferedCopy()?->getId(),
            offeredAt: $request->getOfferedAt(),
        );
    }

    private function portableHandover(Handover $handover): PortableHandover
    {
        return new PortableHandover(
            ref: (int) $handover->getId(),
            copyRef: (int) $handover->getCopy()->getId(),
            toUserId: (int) $handover->getToUser()->getId(),
            openedAt: $handover->getOpenedAt(),
            status: $handover->getStatus(),
            fromUserId: $handover->getFromUser()?->getId(),
            requestRef: $handover->getRequest()?->getId(),
            fromConfirmedAt: $handover->getFromConfirmedAt(),
            toConfirmedAt: $handover->getToConfirmedAt(),
            completedAt: $handover->getCompletedAt(),
            cancelledAt: $handover->getCancelledAt(),
            cancelledByUserId: $handover->getCancelledBy()?->getId(),
        );
    }

    private function portableLedgerEntry(LedgerEntry $entry): PortableLedgerEntry
    {
        $payload = $entry->getPayload();
        $handoverId = $payload[self::PAYLOAD_HANDOVER] ?? null;
        unset($payload[self::PAYLOAD_HANDOVER]);

        return new PortableLedgerEntry(
            type: $entry->getEntryType(),
            context: $entry->getContext(),
            itemType: $entry->getItemType(),
            itemId: $entry->getItemId(),
            occurredAt: $entry->getOccurredAt(),
            copyRef: $entry->getCopyId(),
            fromUserId: $entry->getFromUserId(),
            toUserId: $entry->getToUserId(),
            actorUserId: $entry->getActorUserId(),
            handoverRef: $handoverId === null ? null : (int) $handoverId,
            payload: $payload,
        );
    }

    private function user(?int $userId): ?User
    {
        return $userId === null ? null : $this->em->getReference(User::class, $userId);
    }
}
