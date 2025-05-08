<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use App\Entity\Image;
use App\Service\UploadService;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventFixture extends Fixture implements DependentFixtureInterface
{
    private const bool IS_INITIAL = true;
    private const null NO_RECURRING_OF = null;
    private const null NO_RECURRING_RULE = null;

    public function __construct(
        private readonly UploadService $imageService,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $importUser = $this->getReference('user_' . md5('import'));
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

            // upload a file for thumbnails
            $reference = 'event_' . md5((string)$name);
            $imageFile = __DIR__ . "/Events/$reference.jpg";
            $uploadedImage = new UploadedFile($imageFile, "$reference.jpg");
            $image = $this->imageService->upload($uploadedImage, $importUser);
            $manager->flush();
            if ($image instanceof Image) {
                $this->imageService->createThumbnails($image, [[600, 400]]);
            } else {
                throw new RuntimeException('Unable to upload image: ' . $imageFile);
            }
            $event->setPreviewImage($image);

            $manager->persist($event);
            $this->addReference($reference, $event);
        }
        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            LocationFixture::class,
            HostFixture::class,
            ImageFixture::class,
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
                ['Adem Lane', 'xuedi'],
                ['Adil Floyd', 'Aston Hood', 'Bailey Richards', 'Bec Ferguson', 'Danyal Lester', 'Demi Wilkinson', 'Freya Browning', 'Kaitlin Hale', 'Molly Vaughan', 'Nic Fassbender', 'Orlando Diggs', 'Owen Garcia', 'axisbos audax', 'xuedi', 'Adem Lane', 'Crystal Liu'],
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
                ['Adem Lane', 'xuedi'],
                ['Adil Floyd', 'Lana Steiner', 'Leyton Fields', 'Lyle Kauffman', 'Zuzanna Burke', 'axisbos audax', 'xuedi', 'Adem Lane', 'Crystal Liu'],
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
                ['Adem Lane'],
                ['Amanda Lowery', 'Anita Cruz', 'Florence Shaw', 'Jessie Meyton', 'Jonathan Kelly', 'Marco Kelly', 'Priya Shepard', 'xuedi', 'Adem Lane'],
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
                ['Adem Lane'],
                ['Ayah Wilkinson', 'Billie Wright', 'Herbert Fowler', 'Jay Shepard', 'Leyton Fields', 'Maddison Gillespie', 'Marco Kelly', 'xuedi', 'Adem Lane'],
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
                ['xuedi', 'Adem Lane'],
                ['Crystal Liu', 'Adil Floyd', 'Aston Hood', 'Ayah Wilkinson', 'Jay Shepard', 'Marco Gross', 'Phoenix Baker', 'Rory Huff', 'xuedi'],
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
                ['Adem Lane', 'xuedi'],
                ['Adil Floyd', 'Aysha Becker', 'Bailey Richards', 'Belle Woods', 'Benedict Doherty', 'Eduard Franz', 'Koray Okumus', 'Youssef Roberson', 'xuedi', 'Adem Lane', 'Crystal Liu'],
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
                ['Adem Lane', 'xuedi'],
                ['Benedict Doherty', 'Byron Robertson', 'Isobel Fuller', 'Levi Rocha', 'Nala Goins', 'Priya Shepard', 'Zara Bush', 'xuedi', 'Adem Lane'],
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
}
