<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;

class EventFixture extends Fixture implements DependentFixtureInterface
{
    private const IS_INITIAL = true;
    private const NO_RECURRING_OF = null;
    private const NO_RECURRING_RULE = null;

    public function __construct(private readonly Filesystem $fs)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $data) {
            [$initial, $start, $stop, $name, $recOf, $recRules, $location, $hosts, $rsvps, $type] = $data;
            $event = new Event();
            $event->setInitial($initial);
            $event->setStart($this->setDateType($start));
            $event->setStop($this->setDateType($stop));
            $event->setRecurringOf($recOf);
            $event->setRecurringRule($recRules);
            $event->setUser($this->getReference('user_' . md5('import')));
            $event->setLocation($this->getReference('location_' . md5((string)$location)));
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setType($type);
            foreach ($hosts as $user) {
                $event->addHost($this->getReference('host_' . md5((string)$user)));
            }
            foreach ($rsvps as $user) {
                $event->addRsvp($this->getReference('user_' . md5((string)$user)));
            }

            $manager->persist($event);

            $this->addReference('event_' . md5((string) $name), $event);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            LocationFixture::class,
            HostFixture::class,
            ImageFixture::class,
        ];
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
                ['易木', '雪地'],
                ['xuedi', 'yimu', 'Crystal Liu'],
                EventTypes::Regular,
            ],
            [
                self::IS_INITIAL,
                '2015-09-18 20:00',
                '2015-09-18 22:30',
                'Spicy Chinese dinner at a Sichuan restaurant',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Grand Tang',
                ['易木', '雪地'],
                ['xuedi', 'yimu'],
                EventTypes::Dinner,
            ],
            [
                self::IS_INITIAL,
                '2015-09-26 17:00',
                '2015-09-26 20:30',
                '中秋节 - Mid Autumn festival',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Garten der Welt',
                ['易木'],
                ['xuedi', 'yimu', 'Crystal Liu'],
                EventTypes::Outdoor,
            ],
            [
                self::IS_INITIAL,
                '2016-07-01 19:30',
                '2016-07-01 20:30',
                '下馆子！Let’s go eat!',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Lao Xiang',
                ['易木'],
                ['yimu', 'Crystal Liu'],
                EventTypes::Dinner,
            ],
            [
                self::IS_INITIAL,
                '2020-09-03 19:00',
                '2020-09-03 20:00',
                'Outdoor Meetup at Himmelbeet',
                self::NO_RECURRING_OF,
                self::NO_RECURRING_RULE,
                'Himmelbeet',
                ['雪地', '易木'],
                ['xuedi', 'yimu'],
                EventTypes::Outdoor,
            ],
            [
                self::IS_INITIAL,
                '2024-09-19 19:00',
                '2024-09-19 22:30',
                '定期活动 - Regular meetup',
                self::NO_RECURRING_OF,
                EventIntervals::BiMonthly,
                'Volksbar',
                ['易木', '雪地'],
                ['xuedi', 'yimu', 'Crystal Liu'],
                EventTypes::Regular,
            ],
            [
                self::IS_INITIAL,
                '2025-02-26 19:30',
                '2025-02-26 22:30',
                '生日快乐 - Meetup get one year older',
                self::NO_RECURRING_OF,
                EventIntervals::Yearly,
                'Volksbar',
                ['易木', '雪地'],
                ['xuedi', 'yimu'],
                EventTypes::Dinner,
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

    private function getBlob(string $string): string
    {
        return $this->fs->readFile(__DIR__ . "/blobs/Event_$string.txt");
    }
}
