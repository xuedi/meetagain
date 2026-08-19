<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Repository\EventRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RsvpGuestFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    private const int EVENTS = 3;
    private const array COUNTS = [2, 1, 3];

    public static function getGroups(): array
    {
        return ['base'];
    }

    /**
     * @return array<class-string<FixtureInterface>>
     */
    public function getDependencies(): array
    {
        return [EventFixture::class];
    }

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        echo 'Creating RSVP guests ... ';

        $created = 0;
        foreach ($this->eventsWithRsvps() as $event) {
            foreach (array_values($event->getRsvp()->toArray()) as $index => $user) {
                if (!isset(self::COUNTS[$index])) {
                    break;
                }

                $guest = new RsvpGuest($event, $user);
                for ($i = 0; $i < self::COUNTS[$index]; $i++) {
                    $guest->increment();
                }

                $manager->persist($guest);
                $created++;
            }
        }

        $manager->flush();

        echo $created > 0 ? 'OK' . PHP_EOL : 'SKIP (no event with RSVPs)' . PHP_EOL;
    }

    /**
     * @return list<Event>
     */
    private function eventsWithRsvps(): array
    {
        $events = array_filter(
            $this->eventRepository->findAll(),
            static fn(Event $event): bool => $event->getRsvp()->count() > 0,
        );

        usort($events, static fn(Event $a, Event $b): int => $b->getRsvp()->count() <=> $a->getRsvp()->count());

        return array_slice(array_values($events), 0, self::EVENTS);
    }
}
