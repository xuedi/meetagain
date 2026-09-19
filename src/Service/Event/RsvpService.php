<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Activity\ActivityService;
use App\Activity\Messages\RsvpNo;
use App\Activity\Messages\RsvpYes;
use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use App\Enum\RsvpRefusal;
use App\Exception\Event\RsvpRefusedException;
use App\Filter\Event\EventFilterService;
use App\Repository\RsvpGuestRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class RsvpService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RsvpGuestRepository $repo,
        private ActivityService $activityService,
        private EventFilterService $eventFilterService,
        private Security $security,
    ) {}

    public function toggle(Event $event, User $user): bool
    {
        return $this->setGoing($event, $user, !$event->hasRsvp($user));
    }

    public function setGoing(Event $event, User $user, bool $going, ?int $guests = null): bool
    {
        if ($going) {
            $this->assertMayRsvp($event, $user);
        }

        if ($guests !== null && ($guests < 0 || $guests > RsvpGuest::MAX_GUESTS)) {
            throw new RsvpRefusedException(RsvpRefusal::GuestCountInvalid);
        }

        if ($event->hasRsvp($user) !== $going) {
            $event->toggleRsvp($user);
            $this->em->persist($event);
            $this->em->flush();
            $this->activityService->log($going ? RsvpYes::TYPE : RsvpNo::TYPE, $user, ['event_id' => $event->getId()]);
        }

        if (!$going) {
            $this->clearGuests($event, $user);

            return false;
        }

        if ($guests !== null) {
            $this->setGuests($event, $user, $guests);
        }

        return true;
    }

    public function changeGuests(Event $event, User $user, string $direction): int
    {
        if ($direction === 'add') {
            $this->assertMayRsvp($event, $user);
        }
        if (!$event->hasRsvp($user)) {
            throw new RsvpRefusedException(RsvpRefusal::NotGoing);
        }

        $row = $this->row($event, $user);
        if ($direction === 'add') {
            $row->increment();
        } else {
            $row->decrement();
        }

        return $this->store($row);
    }

    public function guestsOf(Event $event, User $user): int
    {
        return $this->repo->findOneBy(['event' => $event, 'user' => $user])?->getGuests() ?? 0;
    }

    public function clearGuests(Event $event, User $user): void
    {
        $this->repo->deleteFor($event, $user);
    }

    private function setGuests(Event $event, User $user, int $guests): void
    {
        $row = $this->row($event, $user);
        while ($row->getGuests() < $guests) {
            $row->increment();
        }
        while ($row->getGuests() > $guests) {
            $row->decrement();
        }

        $this->store($row);
    }

    private function row(Event $event, User $user): RsvpGuest
    {
        $row = $this->repo->findOneBy(['event' => $event, 'user' => $user]);
        if ($row === null) {
            $row = new RsvpGuest($event, $user);
            $this->em->persist($row);
        }

        return $row;
    }

    private function store(RsvpGuest $row): int
    {
        if ($row->getGuests() === 0) {
            $this->em->remove($row);
            $this->em->flush();

            return 0;
        }

        $this->em->flush();

        return $row->getGuests();
    }

    private function assertMayRsvp(Event $event, User $user): void
    {
        $refusal = match (true) {
            !$this->eventFilterService->isEventAccessible((int) $event->getId()) => RsvpRefusal::Inaccessible,
            !$this->security->isGranted(PermissionAttribute::EVENT_RSVP, $event) => RsvpRefusal::NotAllowed,
            $event->isCanceled() => RsvpRefusal::Canceled,
            $event->getStart() < new DateTimeImmutable() => RsvpRefusal::Started,
            default => null,
        };

        if ($refusal !== null) {
            throw new RsvpRefusedException($refusal);
        }
    }
}
