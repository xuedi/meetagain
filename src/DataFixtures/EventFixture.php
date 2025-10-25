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

class EventFixture extends AbstractFixture implements DependentFixtureInterface
{
    private const bool IS_INITIAL = true;
    private const null NO_RECURRING_OF = null;
    private const null NO_RECURRING_RULE = null;

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating events ... ';
        foreach ($this->getData() as $data) {
            [$start, $stop, $name, $recOf, $recRules, $location, $hosts, $rsvps, $type, $featured] = $data;
            $event = new Event();
            $event->setInitial(true);
            $event->setPublished(true);
            $event->setFeatured($featured);
            $event->setStart($start);
            $event->setStop($stop);
            $event->setRecurringOf($recOf);
            $event->setRecurringRule($recRules);
            $event->setUser($this->getReference('user_' . md5('import'), User::class));
            $event->setLocation($this->getReference('LocationFixture::' . md5((string) $location), Location::class));
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setType($type);
            foreach ($hosts as $user) {
                $event->addHost($this->getReference('HostFixture::' . md5((string) $user), Host::class));
            }
            foreach ($rsvps as $user) {
                $event->addRsvp($this->getReference('UserFixture::' . md5((string) $user), User::class));
            }

            $manager->persist($event);
            $this->addReference('event_' . md5((string) $name), $event);
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
                new DateTime('2015-02-26 19:00'),
                new DateTime('2015-02-26 22:30'),
                'Let\'s meet up and talk Chinese!',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'St. Oberholz',
                ['Adem Lane', 'admin'],
                [
                    'Adil Floyd',
                    'Aston Hood',
                    'Bailey Richards',
                    'Bec Ferguson',
                    'Danyal Lester',
                    'Demi Wilkinson',
                    'Freya Browning',
                    'Kaitlin Hale',
                    'Molly Vaughan',
                    'Nic Fassbender',
                    'Orlando Diggs',
                    'Owen Garcia',
                    'axisbos audax',
                    'admin',
                    'Adem Lane',
                    'Crystal Liu',
                ],
                EventTypes::Regular,
                true,
            ],
        ];
    }
}
