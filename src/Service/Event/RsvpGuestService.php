<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use App\Repository\RsvpGuestRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RsvpGuestService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RsvpGuestRepository $repo,
    ) {}

    public function change(Event $event, User $user, string $direction): ?int
    {
        if (!$event->hasRsvp($user)) {
            return null;
        }

        $row = $this->repo->findOneBy(['event' => $event, 'user' => $user]);
        if ($direction === 'add') {
            if ($row === null) {
                $row = new RsvpGuest($event, $user);
                $row->increment();
                $this->em->persist($row);
            } else {
                $row->increment();
            }
            $this->em->flush();

            return $row->getGuests();
        }

        if ($row === null) {
            return 0;
        }
        $row->decrement();
        if ($row->getGuests() === 0) {
            $this->em->remove($row);
            $this->em->flush();

            return 0;
        }
        $this->em->flush();

        return $row->getGuests();
    }

    public function onRsvpRemoved(Event $event, User $user): void
    {
        $this->repo->deleteFor($event, $user);
    }
}
