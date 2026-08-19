<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\RsvpGuest;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class RsvpGuestFixture extends Fixture implements FixtureGroupInterface
{
    private const array GUESTS = [
        [69, 'Adem Lane', 2],
        [10, 'Phoenix Baker', 2],
        [10, 'Lana Steiner', 1],
        [7, 'Alisa Hester', 3],
    ];

    public static function getGroups(): array
    {
        return ['base'];
    }

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EventRepository $eventRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        echo 'Creating RSVP guests ... ';

        $created = 0;
        foreach (self::GUESTS as [$eventId, $userName, $count]) {
            $event = $this->eventRepository->find($eventId);
            $user = $this->userRepository->findOneBy(['name' => $userName]);
            if ($event === null || $user === null) {
                continue;
            }

            $guest = new RsvpGuest($event, $user);
            for ($i = 0; $i < $count; $i++) {
                $guest->increment();
            }

            $manager->persist($guest);
            $created++;
        }

        $manager->flush();

        echo $created > 0 ? 'OK' . PHP_EOL : 'SKIP (event or user not found)' . PHP_EOL;
    }
}
