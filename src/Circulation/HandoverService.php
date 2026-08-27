<?php declare(strict_types=1);

namespace App\Circulation;

use App\Activity\ActivityService;
use App\Activity\Messages\CompletedHandover;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationCopyStatus;
use App\Enum\CirculationHandoverStatus;
use App\Enum\CirculationLedgerEntryType;
use App\Enum\CirculationRequestStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final readonly class HandoverService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LedgerService $ledger,
        private ActivityService $activityService,
    ) {}

    public function open(CirculationCopy $copy, ?User $fromUser, User $toUser, ?CirculationRequest $request = null): CirculationHandover
    {
        $now = new DateTimeImmutable();
        $handover = new CirculationHandover($copy, $fromUser, $toUser, $now);
        $handover->setRequest($request);

        $copy->setStatus(CirculationCopyStatus::InHandover);

        $this->em->persist($handover);
        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::HandoverOpened,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            $fromUser?->getId(),
            $toUser->getId(),
            null,
            ['handoverId' => $handover->getId()],
        );

        return $handover;
    }

    public function confirm(CirculationHandover $handover, User $user): void
    {
        if ($handover->getStatus() !== CirculationHandoverStatus::Open) {
            throw new RuntimeException('circulation.flash_handover_closed');
        }
        if (!$handover->isParticipant($user)) {
            throw new RuntimeException('circulation.flash_not_participant');
        }
        if ($handover->hasConfirmed($user)) {
            return;
        }

        $now = new DateTimeImmutable();
        if ($handover->getFromUser()?->getId() === $user->getId()) {
            $handover->setFromConfirmedAt($now);
        } else {
            $handover->setToConfirmedAt($now);
        }
        $this->em->flush();

        $copy = $handover->getCopy();
        $this->ledger->append(
            CirculationLedgerEntryType::HandoverConfirmed,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            $handover->getFromUser()?->getId(),
            $handover->getToUser()->getId(),
            $user->getId(),
            ['handoverId' => $handover->getId()],
        );

        if ($this->bothSidesConfirmed($handover)) {
            $this->complete($handover, $user, $now);
        }
    }

    public function cancel(CirculationHandover $handover, User $user): void
    {
        if ($handover->getStatus() !== CirculationHandoverStatus::Open) {
            return;
        }

        $now = new DateTimeImmutable();
        $handover->setStatus(CirculationHandoverStatus::Cancelled);
        $handover->setCancelledAt($now);
        $handover->setCancelledBy($user);
        $this->releaseCopy($handover);
        $this->em->flush();

        $copy = $handover->getCopy();
        $this->ledger->append(
            CirculationLedgerEntryType::HandoverCancelled,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            $handover->getFromUser()?->getId(),
            $handover->getToUser()->getId(),
            $user->getId(),
            ['handoverId' => $handover->getId()],
        );
    }

    public function expire(CirculationHandover $handover): void
    {
        if ($handover->getStatus() !== CirculationHandoverStatus::Open) {
            return;
        }

        $now = new DateTimeImmutable();
        $handover->setStatus(CirculationHandoverStatus::Expired);
        $handover->setCancelledAt($now);
        $this->releaseCopy($handover);
        $this->em->flush();

        $copy = $handover->getCopy();
        $this->ledger->append(
            CirculationLedgerEntryType::HandoverCancelled,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            $handover->getFromUser()?->getId(),
            $handover->getToUser()->getId(),
            null,
            ['handoverId' => $handover->getId(), 'expired' => true],
        );
    }

    private function bothSidesConfirmed(CirculationHandover $handover): bool
    {
        $giverDone = $handover->getFromUser() === null || $handover->getFromConfirmedAt() !== null;

        return $giverDone && $handover->getToConfirmedAt() !== null;
    }

    private function complete(CirculationHandover $handover, User $actor, DateTimeImmutable $now): void
    {
        $copy = $handover->getCopy();
        $copy->setHolder($handover->getToUser());
        $copy->setHeldSince($now);
        $copy->setFinishedAt(null);
        $copy->setStatus(CirculationCopyStatus::Held);

        $handover->setStatus(CirculationHandoverStatus::Completed);
        $handover->setCompletedAt($now);

        $request = $handover->getRequest();
        $request?->setStatus(CirculationRequestStatus::Fulfilled);

        $this->em->flush();

        $this->ledger->append(
            CirculationLedgerEntryType::HandoverCompleted,
            $copy->getContext(),
            $copy->getItemType(),
            $copy->getItemId(),
            $now,
            $copy->getId(),
            $handover->getFromUser()?->getId(),
            $handover->getToUser()->getId(),
            $actor->getId(),
            ['handoverId' => $handover->getId()],
        );

        $this->activityService->log(CompletedHandover::TYPE, $handover->getToUser(), [
            'item_type' => $copy->getItemType(),
            'item_id' => $copy->getItemId(),
        ]);
    }

    private function releaseCopy(CirculationHandover $handover): void
    {
        $copy = $handover->getCopy();
        if ($copy->getStatus() === CirculationCopyStatus::InHandover) {
            $copy->setStatus(CirculationCopyStatus::Available);
        }

        $request = $handover->getRequest();
        if ($request !== null && $request->getStatus() === CirculationRequestStatus::Offered) {
            $request->setStatus(CirculationRequestStatus::Waiting);
            $request->setOfferedCopy(null);
            $request->setOfferedAt(null);
        }
    }
}
