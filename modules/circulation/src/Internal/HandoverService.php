<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Activity\ActivityService;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\LedgerEntryType;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Activity\CompletedHandover;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Entity\Request;
use RuntimeException;

final readonly class HandoverService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LedgerService $ledger,
        private ActivityService $activityService,
    ) {}

    public function open(Copy $copy, ?User $fromUser, User $toUser, ?Request $request = null): Handover
    {
        $now = new DateTimeImmutable();
        $handover = new Handover($copy, $fromUser, $toUser, $now);
        $handover->setRequest($request);

        $copy->setStatus(CopyStatus::InHandover);

        $this->em->persist($handover);
        $this->em->flush();

        $this->ledger->append(
            LedgerEntryType::HandoverOpened,
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

    public function confirm(Handover $handover, User $user): void
    {
        if ($handover->getStatus() !== HandoverStatus::Open) {
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
            LedgerEntryType::HandoverConfirmed,
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

    public function cancel(Handover $handover, User $user): void
    {
        if ($handover->getStatus() !== HandoverStatus::Open) {
            return;
        }

        $now = new DateTimeImmutable();
        $handover->setStatus(HandoverStatus::Cancelled);
        $handover->setCancelledAt($now);
        $handover->setCancelledBy($user);
        $this->releaseCopy($handover);
        $this->em->flush();

        $copy = $handover->getCopy();
        $this->ledger->append(
            LedgerEntryType::HandoverCancelled,
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

    public function expire(Handover $handover): void
    {
        if ($handover->getStatus() !== HandoverStatus::Open) {
            return;
        }

        $now = new DateTimeImmutable();
        $handover->setStatus(HandoverStatus::Expired);
        $handover->setCancelledAt($now);
        $this->releaseCopy($handover);
        $this->em->flush();

        $copy = $handover->getCopy();
        $this->ledger->append(
            LedgerEntryType::HandoverCancelled,
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

    private function bothSidesConfirmed(Handover $handover): bool
    {
        $giverDone = $handover->getFromUser() === null || $handover->getFromConfirmedAt() !== null;

        return $giverDone && $handover->getToConfirmedAt() !== null;
    }

    private function complete(Handover $handover, User $actor, DateTimeImmutable $now): void
    {
        $copy = $handover->getCopy();
        $copy->setHolder($handover->getToUser());
        $copy->setHeldSince($now);
        $copy->setFinishedAt(null);
        $copy->setStatus(CopyStatus::Held);

        $handover->setStatus(HandoverStatus::Completed);
        $handover->setCompletedAt($now);

        $request = $handover->getRequest();
        $request?->setStatus(RequestStatus::Fulfilled);

        $this->em->flush();

        $this->ledger->append(
            LedgerEntryType::HandoverCompleted,
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

    private function releaseCopy(Handover $handover): void
    {
        $copy = $handover->getCopy();
        if ($copy->getStatus() === CopyStatus::InHandover) {
            $copy->setStatus(CopyStatus::Available);
        }

        $request = $handover->getRequest();
        if ($request !== null && $request->getStatus() === RequestStatus::Offered) {
            $request->setStatus(RequestStatus::Waiting);
            $request->setOfferedCopy(null);
            $request->setOfferedAt(null);
        }
    }
}
