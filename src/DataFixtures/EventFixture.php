<?php

declare(strict_types=1);

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
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventFixture extends AbstractFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const string WEDNESDAY_MEETUP = 'Regular Wednesday meetup';

    public function __construct(private readonly ImageService $imageService)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
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

            // upload file and create thumbnails
            $imageFile = __DIR__ . "/Event/" . $data['previewImage'];
            $uploadedImage = new UploadedFile($imageFile, $event->getId() . '.jpg');
            $image = $this->imageService->upload($uploadedImage, $data['createdBy'], ImageType::EventTeaser);
            $this->imageService->createThumbnails($image);
            $event->setPreviewImage($image);
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

    public static function getGroups(): array
    {
        return ['base'];
    }

    private function getData(): array
    {
        return [
            [
                'start' => $this->getWednesdayMeetupDate(),
                'stop' => $this->getWednesdayMeetupDate()->modify('+3 hour'),
                'name' => self::WEDNESDAY_MEETUP,
                'location' => $this->getRefLocation(LocationFixture::SPREE_BLICK),
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
                        'title' => self::WEDNESDAY_MEETUP,
                        'teaser' => $this->getText('wednesday_meetup_teaser_en'),
                        'description' => $this->getText('wednesday_meetup_description_en'),
                    ],
                    'de' => [
                        'title' => 'Mittwochs-Treffen',
                        'teaser' => $this->getText('wednesday_meetup_teaser_de'),
                        'description' => $this->getText('wednesday_meetup_description_de'),
                    ],
                    'cn' => [
                        'title' => '周三聚会',
                        'teaser' => $this->getText('wednesday_meetup_teaser_cn'),
                        'description' => $this->getText('wednesday_meetup_description_cn'),
                    ],
                ],
                'comments' => [
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+18 hour'),
                        'user' => UserFixture::ADMIN,
                        'msg' => 'Cool event',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+20 hour'),
                        'user' => UserFixture::ADEM_LANE,
                        'msg' => 'it was, but very lonely',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+21 hour'),
                        'user' => UserFixture::ADMIN,
                        'msg' => '@Adem, we for sure won a lot',
                    ],
                    [
                        'date' => $this->getWednesdayMeetupDate()->modify('+22 hour'),
                        'user' => UserFixture::CRYSTAL_LIU,
                        'msg' => 'Next time i will have my revenge',
                    ],
                ]
            ],
        ];
    }

    private function getWednesdayMeetupDate(): DateTime
    {
        return new DateTime('now')
            ->modify('-7 days')
            ->modify('first wednesday')
            ->setTime(18, 00);
    }
}
