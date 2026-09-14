<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class RsvpsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'rsvps';
    }

    #[Override]
    public function getOrder(): int
    {
        return 45;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $events = $this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds], ['id' => 'ASC']);
        $guests = $this->guestsByEventAndUser($events);

        $rows = [];
        foreach ($events as $event) {
            foreach ($event->getRsvp() as $user) {
                if (!$scope->grants($user, DataCategory::Attendance)) {
                    continue;
                }

                $rows[] = [
                    'event_ref' => (int) $event->getId(),
                    'email' => (string) $user->getEmail(),
                    'guests' => $guests[$event->getId() . '-' . $user->getId()] ?? 0,
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int => [$a['event_ref'], $a['email']] <=> [$b['event_ref'], $b['email']]);

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $event = $context->resolveRef(Event::class, $row['event_ref'] ?? null);
            $user = $context->resolveRef(User::class, $row['email'] ?? null);
            if (!$event instanceof Event || !$user instanceof User) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $event->addRsvp($user);

            $guestCount = max(0, min(RsvpGuest::MAX_GUESTS, (int) ($row['guests'] ?? 0)));
            if ($guestCount > 0) {
                $guest = new RsvpGuest($event, $user);
                for ($i = 0; $i < $guestCount; ++$i) {
                    $guest->increment();
                }
                $this->em->persist($guest);
            }

            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param list<Event> $events
     * @return array<string, int>
     */
    private function guestsByEventAndUser(array $events): array
    {
        if ($events === []) {
            return [];
        }

        $guests = [];
        foreach ($this->em->getRepository(RsvpGuest::class)->findBy(['event' => $events]) as $guest) {
            $guests[$guest->getEvent()->getId() . '-' . $guest->getUser()->getId()] = $guest->getGuests();
        }

        return $guests;
    }
}
