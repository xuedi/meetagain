<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTranslation;
use App\Entity\EventTypes;
use App\Entity\ImageType;
use App\Service\ImageService;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string WEEKLY_GO_STUDY = 'Weekly Go Study Group';
    public const string BERLIN_TOURNAMENT = 'Berlin Go Tournament 2026';
    public const string BEGINNER_WORKSHOP = 'Beginner Workshop: Learn Go';
    public const string ONLINE_SIMULTANEOUS = 'Online Simultaneous Game with 5-Dan';
    public const string WEEKEND_RETREAT = 'Weekend Go Retreat';

    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->start();

        // Load the preview image once and reuse it for all events
        $imageFile = __DIR__ . '/Event/preview_wednesday_meetup.jpg';
        $uploadedImage = new UploadedFile($imageFile, 'preview_wednesday_meetup.jpg');
        $previewImage = $this->imageService->upload(
            $uploadedImage,
            $this->getRefUser(UserFixture::ADMIN),
            ImageType::EventTeaser,
        );
        $this->imageService->createThumbnails($previewImage);

        foreach ($this->getData() as $data) {
            $event = new Event();
            $event->setInitial(true);
            $event->setPublished(true);
            $event->setFeatured($data['featured'] ?? false);
            $event->setStart($data['start']);
            $event->setStop($data['stop']);
            $event->setRecurringOf($data['recurringOf'] ?? null);
            $event->setRecurringRule($data['recurringRule'] ?? null);
            $event->setUser($data['createdBy']);
            $event->setLocation($data['location']);
            $event->setCreatedAt(new DateTimeImmutable());
            $event->setType($data['type']);

            foreach ($data['hosts'] as $host) {
                $event->addHost($this->getRefHost($host));
            }
            foreach ($data['rsvps'] as $rsvpUser) {
                $event->addRsvp($this->getRefUser($rsvpUser));
            }

            // Use the pre-loaded image instead of uploading each time
            $event->setPreviewImage($previewImage);
            $manager->persist($event);

            // add contents
            foreach ($data['content'] as $language => $contentData) {
                $eventTranslation = new EventTranslation();
                $eventTranslation->setEvent($event);
                $eventTranslation->setLanguage($language);
                $eventTranslation->setTitle($contentData['title']);
                $eventTranslation->setTeaser($contentData['teaser']);
                $eventTranslation->setDescription($contentData['description']);
                $manager->persist($eventTranslation);
            }

            // add comments
            foreach ($data['comments'] as $commentData) {
                $comment = new Comment();
                $comment->setEvent($event);
                $comment->setUser($this->getRefUser($commentData['user']));
                $comment->setCreatedAt(DateTimeImmutable::createFromMutable($commentData['date']));
                $comment->setContent($commentData['msg']);
                $manager->persist($comment);
            }

            $manager->persist($event);
            $this->addRefEvent($data['name'], $event);
        }
        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            LocationFixture::class,
            HostFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            // 1. Weekly Go Study Group - Main recurring event
            [
                'start' => $this->getWednesdayMeetupDate(),
                'stop' => $this->getWednesdayMeetupDate()->modify('+3 hour'),
                'name' => self::WEEKLY_GO_STUDY,
                'location' => $this->getRefLocation(LocationFixture::WEIQI_CAFE),
                'type' => EventTypes::Regular,
                'featured' => true,
                'recurringRule' => EventIntervals::Weekly,
                'createdBy' => $this->getRefUser(UserFixture::ADEM_LANE),
                'previewImage' => 'preview_wednesday_meetup.jpg',
                'hosts' => [
                    HostFixture::ADMIN,
                    HostFixture::ADEM,
                    HostFixture::CRYSTAL,
                ],
                'rsvps' => [
                    UserFixture::ADMIN,
                    UserFixture::CRYSTAL_LIU,
                    UserFixture::ADEM_LANE,
                    UserFixture::ALISA_HESTER,
                    UserFixture::JESSIE_MEYTON,
                    UserFixture::MOLLIE_HALL,
                ],
                'content' => [
                    'en' => [
                        'title' => self::WEEKLY_GO_STUDY,
                        'teaser' => $this->getText('wednesday_meetup_teaser_en'),
                        'description' => $this->getText('wednesday_meetup_description_en'),
                    ],
                    'de' => [
                        'title' => 'Wöchentliche Go-Studiengruppe',
                        'teaser' => $this->getText('wednesday_meetup_teaser_de'),
                        'description' => $this->getText('wednesday_meetup_description_de'),
                    ],
                    'cn' => [
                        'title' => '每周围棋学习小组',
                        'teaser' => $this->getText('wednesday_meetup_teaser_cn'),
                        'description' => $this->getText('wednesday_meetup_description_cn'),
                    ],
                ],
                'comments' => [
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+18 hour'),
                        'user' => UserFixture::ADMIN,
                        'msg' => 'Great game analysis session tonight!',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+20 hour'),
                        'user' => UserFixture::ADEM_LANE,
                        'msg' => 'Thanks for teaching me the avalanche joseki',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+21 hour'),
                        'user' => UserFixture::ADMIN,
                        'msg' => '@Adem, we should practice that next week',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+22 hour'),
                        'user' => UserFixture::CRYSTAL_LIU,
                        'msg' => 'Next time I will challenge you to a handicap game',
                    ],
                ],
            ],

            // 2. Berlin Go Tournament 2026
            [
                'start' => new DateTime('now')
                    ->modify('+2 months')
                    ->modify('first saturday')
                    ->setTime(9, 0),
                'stop' => new DateTime('now')
                    ->modify('+2 months')
                    ->modify('first saturday')
                    ->setTime(18, 0),
                'name' => self::BERLIN_TOURNAMENT,
                'location' => $this->getRefLocation(LocationFixture::COMMUNITY_CENTER),
                'type' => EventTypes::Regular,
                'featured' => true,
                'createdBy' => $this->getRefUser(UserFixture::ADMIN),
                'previewImage' => 'preview_wednesday_meetup.jpg',
                'hosts' => [
                    HostFixture::ADMIN,
                    HostFixture::JESSIE,
                ],
                'rsvps' => [
                    UserFixture::ADMIN,
                    UserFixture::ADEM_LANE,
                    UserFixture::CRYSTAL_LIU,
                    UserFixture::ALISA_HESTER,
                ],
                'content' => [
                    'en' => [
                        'title' => self::BERLIN_TOURNAMENT,
                        'teaser' => 'Annual city championship for all skill levels. McMahon pairing system, 5 rounds.',
                        'description' => 'Join us for the annual Berlin Go Tournament! Open to all skill levels with separate divisions. McMahon pairing system ensures fair games. Entry fee: €15 includes lunch. Prizes for top 3 in each division.',
                    ],
                    'de' => [
                        'title' => 'Berliner Go-Turnier 2026',
                        'teaser' => 'Jährliche Stadtmeisterschaft für alle Spielstärken. McMahon-Paarungssystem, 5 Runden.',
                        'description' => 'Nehmen Sie am jährlichen Berliner Go-Turnier teil! Offen für alle Spielstärken mit separaten Divisionen. McMahon-Paarungssystem garantiert faire Spiele. Teilnahmegebühr: 15€ inkl. Mittagessen. Preise für die Top 3 in jeder Division.',
                    ],
                    'cn' => [
                        'title' => '2026年柏林围棋锦标赛',
                        'teaser' => '年度城市锦标赛，所有水平均可参加。麦克马洪配对系统，5轮比赛。',
                        'description' => '参加年度柏林围棋锦标赛！对所有水平开放，设有不同组别。麦克马洪配对系统确保公平对局。参赛费：15欧元含午餐。各组别前三名有奖。',
                    ],
                ],
                'comments' => [
                    [
                        'date' => new DateTime('now')->modify('+1 month'),
                        'user' => UserFixture::ALISA_HESTER,
                        'msg' => 'Looking forward to this! Is there a dan division?',
                    ],
                    [
                        'date' => new DateTime('now')
                            ->modify('+1 month')
                            ->modify('+2 hours'),
                        'user' => UserFixture::ADMIN,
                        'msg' => 'Yes! Dan and kyu divisions, both use same McMahon system.',
                    ],
                ],
            ],

            // 3. Beginner Workshop: Learn Go
            [
                'start' => new DateTime('now')
                    ->modify('+1 month')
                    ->modify('first saturday')
                    ->setTime(14, 0),
                'stop' => new DateTime('now')
                    ->modify('+1 month')
                    ->modify('first saturday')
                    ->setTime(17, 0),
                'name' => self::BEGINNER_WORKSHOP,
                'location' => $this->getRefLocation(LocationFixture::WEIQI_CAFE),
                'type' => EventTypes::Regular,
                'recurringRule' => EventIntervals::Monthly,
                'createdBy' => $this->getRefUser(UserFixture::CRYSTAL_LIU),
                'previewImage' => 'preview_wednesday_meetup.jpg',
                'hosts' => [
                    HostFixture::CRYSTAL,
                    HostFixture::ADEM,
                ],
                'rsvps' => [
                    UserFixture::ALISA_HESTER,
                    UserFixture::MOLLIE_HALL,
                    UserFixture::JESSIE_MEYTON,
                ],
                'content' => [
                    'en' => [
                        'title' => self::BEGINNER_WORKSHOP,
                        'teaser' => 'Complete introduction to Go for absolute beginners. Free event, all materials provided.',
                        'description' => 'Never played Go before? Perfect! This workshop covers the basic rules, strategy fundamentals, and your first games on a 9x9 board. Our certified instructors will guide you step by step. All materials provided, no experience needed. Free event!',
                    ],
                    'de' => [
                        'title' => 'Anfänger-Workshop: Go lernen',
                        'teaser' => 'Vollständige Einführung in Go für absolute Anfänger. Kostenlose Veranstaltung, alle Materialien gestellt.',
                        'description' => 'Noch nie Go gespielt? Perfekt! Dieser Workshop deckt die Grundregeln, strategische Grundlagen und Ihre ersten Spiele auf einem 9x9-Brett ab. Unsere zertifizierten Instruktoren führen Sie Schritt für Schritt. Alle Materialien werden gestellt, keine Erfahrung nötig. Kostenlose Veranstaltung!',
                    ],
                    'cn' => [
                        'title' => '初学者工作坊：学习围棋',
                        'teaser' => '为零基础初学者提供的完整围棋入门。免费活动，提供所有材料。',
                        'description' => '从未下过围棋？太好了！本工作坊涵盖基本规则、策略基础和您在9路棋盘上的首局对弈。我们的认证教练将逐步指导。提供所有材料，无需经验。免费活动！',
                    ],
                ],
                'comments' => [],
            ],

            // 4. Online Simultaneous Game with 5-Dan
            [
                'start' => new DateTime('now')
                    ->modify('+3 weeks')
                    ->modify('saturday')
                    ->setTime(15, 0),
                'stop' => new DateTime('now')
                    ->modify('+3 weeks')
                    ->modify('saturday')
                    ->setTime(18, 0),
                'name' => self::ONLINE_SIMULTANEOUS,
                'location' => $this->getRefLocation(LocationFixture::ONLINE_PLATFORM),
                'type' => EventTypes::Regular,
                'createdBy' => $this->getRefUser(UserFixture::ADMIN),
                'previewImage' => 'preview_wednesday_meetup.jpg',
                'hosts' => [
                    HostFixture::ADMIN,
                ],
                'rsvps' => [
                    UserFixture::ADEM_LANE,
                    UserFixture::CRYSTAL_LIU,
                    UserFixture::ALISA_HESTER,
                    UserFixture::MOLLIE_HALL,
                ],
                'content' => [
                    'en' => [
                        'title' => self::ONLINE_SIMULTANEOUS,
                        'teaser' => 'Watch a 5-dan player take on multiple opponents at once. Commentary included!',
                        'description' => 'Experience the power of a strong player! Our resident 5-dan will play simultaneous games against 6 participants while providing live commentary. Great learning opportunity to understand high-level thinking. Online via OGS platform.',
                    ],
                    'de' => [
                        'title' => 'Online-Simultanspiel mit 5-Dan',
                        'teaser' => 'Sehen Sie einen 5-Dan-Spieler gegen mehrere Gegner gleichzeitig antreten. Mit Kommentar!',
                        'description' => 'Erleben Sie die Kraft eines starken Spielers! Unser ansässiger 5-Dan spielt simultane Partien gegen 6 Teilnehmer und liefert Live-Kommentar. Großartige Lernmöglichkeit, um High-Level-Denken zu verstehen. Online über OGS-Plattform.',
                    ],
                    'cn' => [
                        'title' => '与5段棋手的在线联棋',
                        'teaser' => '观看5段棋手同时对抗多名对手。包含解说！',
                        'description' => '体验强者的力量！我们的常驻5段棋手将同时与6名参与者对弈，并提供现场解说。这是理解高水平思维的绝佳学习机会。通过OGS平台在线进行。',
                    ],
                ],
                'comments' => [
                    [
                        'date' => new DateTime('now')->modify('+2 weeks'),
                        'user' => UserFixture::MOLLIE_HALL,
                        'msg' => 'Can I watch if I don\'t want to play?',
                    ],
                    [
                        'date' => new DateTime('now')
                            ->modify('+2 weeks')
                            ->modify('+1 hour'),
                        'user' => UserFixture::ADMIN,
                        'msg' => 'Absolutely! Spectators are welcome to watch and learn.',
                    ],
                ],
            ],

            // 5. Weekend Go Retreat
            [
                'start' => new DateTime('now')
                    ->modify('+6 weeks')
                    ->modify('friday')
                    ->setTime(16, 0),
                'stop' => new DateTime('now')
                    ->modify('+6 weeks')
                    ->modify('sunday')
                    ->setTime(16, 0),
                'name' => self::WEEKEND_RETREAT,
                'location' => $this->getRefLocation(LocationFixture::GRUNEWALD_CAMPING),
                'type' => EventTypes::Outdoor,
                'createdBy' => $this->getRefUser(UserFixture::ADMIN),
                'previewImage' => 'preview_wednesday_meetup.jpg',
                'hosts' => [
                    HostFixture::ADMIN,
                    HostFixture::CRYSTAL,
                    HostFixture::MOLLIE,
                ],
                'rsvps' => [
                    UserFixture::ADMIN,
                    UserFixture::CRYSTAL_LIU,
                    UserFixture::JESSIE_MEYTON,
                ],
                'content' => [
                    'en' => [
                        'title' => self::WEEKEND_RETREAT,
                        'teaser' => 'Intensive training weekend in nature. Lectures, game reviews, and lots of playing time.',
                        'description' => 'Join us for an immersive Go weekend in the beautiful Grunewald forest! Includes accommodation, meals, structured training sessions, professional lectures, and plenty of time for games. All levels welcome. Limited to 20 participants.',
                    ],
                    'de' => [
                        'title' => 'Wochenend-Go-Retreat',
                        'teaser' => 'Intensives Trainingswochenende in der Natur. Vorträge, Spielanalysen und viel Spielzeit.',
                        'description' => 'Begleiten Sie uns zu einem immersiven Go-Wochenende im schönen Grunewald! Inklusive Unterkunft, Verpflegung, strukturierte Trainingseinheiten, professionelle Vorträge und viel Zeit zum Spielen. Alle Level willkommen. Begrenzt auf 20 Teilnehmer.',
                    ],
                    'cn' => [
                        'title' => '周末围棋静修',
                        'teaser' => '在大自然中进行的密集训练周末。讲座、棋局复盘和大量对弈时间。',
                        'description' => '加入我们在美丽的格鲁内瓦尔德森林度过沉浸式围棋周末！包括住宿、餐饮、结构化训练课程、专业讲座和充足的对弈时间。欢迎所有水平。限20名参与者。',
                    ],
                ],
                'comments' => [],
            ],
        ];
    }

    private function getWednesdayMeetupDate(): DateTime
    {
        $date = new DateTime('now');

        return $date->modify('-2 month')->modify('first wednesday')->setTime(18, 00);
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
