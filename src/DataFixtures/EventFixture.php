<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use App\Entity\Host;
use App\Entity\Location;
use App\Entity\User;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EventFixture extends Fixture implements DependentFixtureInterface
{
    private const bool IS_INITIAL = true;
    private const null NO_RECURRING_OF = null;
    private const null NO_RECURRING_RULE = null;

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating events ... ';
        foreach ($this->getData() as $data) {
            [$initial, $start, $stop, $name, $recOf, $recRules, $location, $hosts, $rsvps, $type, $featured] = $data;
            $event = new Event();
            $event->setInitial($initial);
            $event->setPublished(true);
            $event->setFeatured($featured);
            $event->setStart($this->setDateType($start));
            $event->setStop($this->setDateType($stop));
            $event->setRecurringOf($recOf);
            $event->setRecurringRule($recRules);
            $event->setUser($this->getReference('user_' . md5('import'), User::class));
            $event->setLocation($this->getReference('location_' . md5((string)$location), Location::class));
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setType($type);
            foreach ($hosts as $user) {
                $event->addHost($this->getReference('host_' . md5((string)$user), Host::class));
            }
            foreach ($rsvps as $user) {
                $event->addRsvp($this->getReference('user_' . md5((string)$user), User::class));
            }

            $manager->persist($event);
            $this->addReference('event_' . md5((string)$name), $event);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            LocationFixture::class,
            HostFixture::class,
        ];
    }

    public function getEventNames(): array
    {
        $nameList = [];
        foreach ($this->getData() as $data) {
            $nameList[] = $data[3];
        }

        return $nameList;
    }

    private function getData(): array
    {
        return [
            [
                self::IS_INITIAL,
                '2015-02-26 19:30',
                '2015-02-26 22:30',
                'Let\'s meet up and talk Chinese!',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'St. Oberholz',
                ['Adem Lane', 'admin'],
                ['Adil Floyd', 'Aston Hood', 'Bailey Richards', 'Bec Ferguson', 'Danyal Lester', 'Demi Wilkinson', 'Freya Browning', 'Kaitlin Hale', 'Molly Vaughan', 'Nic Fassbender', 'Orlando Diggs', 'Owen Garcia', 'axisbos audax', 'admin', 'Adem Lane', 'Crystal Liu'],
                EventTypes::Regular,
                true,
            ],
            [
                self::IS_INITIAL,
                '2015-09-18 20:00',
                '2015-09-18 22:30',
                'Spicy Chinese dinner at a Sichuan restaurant',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Grand Tang',
                ['Adem Lane', 'admin'],
                ['Adil Floyd', 'Lana Steiner', 'Leyton Fields', 'Lyle Kauffman', 'Zuzanna Burke', 'axisbos audax', 'admin', 'Adem Lane', 'Crystal Liu'],
                EventTypes::Dinner,
                true,
            ],
            [
                self::IS_INITIAL,
                '2015-09-26 17:00',
                '2015-09-26 20:30',
                '中秋节 - Mid Autumn festival',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Garten der Welt',
                ['Adem Lane'],
                ['Amanda Lowery', 'Anita Cruz', 'Florence Shaw', 'Jessie Meyton', 'Jonathan Kelly', 'Marco Kelly', 'Priya Shepard', 'admin', 'Adem Lane'],
                EventTypes::Outdoor,
                true,
            ],
            [
                self::IS_INITIAL,
                '2016-07-01 19:30',
                '2016-07-01 20:30',
                '下馆子！Let’s go eat!',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Lao Xiang',
                ['Adem Lane'],
                ['Ayah Wilkinson', 'Billie Wright', 'Herbert Fowler', 'Jay Shepard', 'Leyton Fields', 'Maddison Gillespie', 'Marco Kelly', 'admin', 'Adem Lane'],
                EventTypes::Dinner,
                true,
            ],
            [
                self::IS_INITIAL,
                '2020-09-03 19:00',
                '2020-09-03 20:00',
                'Outdoor Meetup at Himmelbeet',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Himmelbeet',
                ['admin', 'Adem Lane'],
                ['Crystal Liu', 'Adil Floyd', 'Aston Hood', 'Ayah Wilkinson', 'Jay Shepard', 'Marco Gross', 'Phoenix Baker', 'Rory Huff', 'admin'],
                EventTypes::Outdoor,
                true,
            ],
            [
                self::IS_INITIAL,
                '2024-09-19 19:00',
                '2024-09-19 22:30',
                '定期活动 - Regular meetup',
                self::NO_RECURRING_OF,
                EventIntervals::BiMonthly,
                'Volksbar',
                ['Adem Lane', 'admin'],
                ['Adil Floyd', 'Aysha Becker', 'Bailey Richards', 'Belle Woods', 'Benedict Doherty', 'Eduard Franz', 'Koray Okumus', 'Youssef Roberson', 'admin', 'Adem Lane', 'Crystal Liu'],
                EventTypes::Regular,
                false,
            ],
            [
                self::IS_INITIAL,
                '2025-02-26 19:30',
                '2025-02-26 22:30',
                '生日快乐 - Meetup get one year older',
                self::NO_RECURRING_OF,
                EventIntervals::Yearly,
                'Volksbar',
                ['Adem Lane', 'admin'],
                ['Benedict Doherty', 'Byron Robertson', 'Isobel Fuller', 'Levi Rocha', 'Nala Goins', 'Priya Shepard', 'Zara Bush', 'admin', 'Adem Lane'],
                EventTypes::Dinner,
                true,
            ],
        ];
    }

    private function setDateType(?string $text): ?DateTime
    {
        if ($text === null || $text === '' || $text === '0') {
            return null;
        }

        return new DateTime($text);
    }
}
