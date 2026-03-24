<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Enum\ActivityType;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ActivityFixture extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$time, $userName, $type, $meta]) {
            $activity = new Activity();
            $activity->setUser($this->getRefUser($userName));
            $activity->setCreatedAt(new DateTimeImmutable($time));
            $activity->setType($type);
            $activity->setMeta($meta);

            $manager->persist($activity);
        }
        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            EventFixture::class,
            CmsFixture::class,
        ];
    }

    private function getData(): array
    {
        $weeklyGoStudy    = $this->getRefEvent(EventFixture::WEEKLY_GO_STUDY)->getId();
        $berlinTournament = $this->getRefEvent(EventFixture::BERLIN_TOURNAMENT)->getId();
        $beginnerWorkshop = $this->getRefEvent(EventFixture::BEGINNER_WORKSHOP)->getId();
        $aboutCmsId       = $this->getRefCms(CmsFixture::ABOUT)->getId();
        $ademLaneId       = $this->getRefUser(UserFixture::ADEM_LANE)->getId();
        $crystalLiuId     = $this->getRefUser(UserFixture::CRYSTAL_LIU)->getId();

        return [
            [
                '2025-01-01 10:00:00',
                UserFixture::ADMIN,
                ActivityType::Registered,
                null,
            ],
            [
                '2025-01-02 10:00:00',
                UserFixture::ADMIN,
                ActivityType::Login,
                null,
            ],
            [
                '2025-01-03 10:00:00',
                UserFixture::ADMIN,
                ActivityType::RsvpYes,
                ['event_id' => $weeklyGoStudy],
            ],
            [
                '2025-01-04 10:00:00',
                UserFixture::ADMIN,
                ActivityType::RsvpYes,
                ['event_id' => $berlinTournament],
            ],
            [
                '2025-01-04 12:30:00',
                UserFixture::ADMIN,
                ActivityType::RsvpYes,
                ['event_id' => $beginnerWorkshop],
            ],
            [
                '2025-01-11 10:00:00',
                UserFixture::ADEM_LANE,
                ActivityType::Registered,
                null,
            ],
            [
                '2025-01-12 10:00:00',
                UserFixture::ADEM_LANE,
                ActivityType::Login,
                null,
            ],
            [
                '2025-01-13 10:00:00',
                UserFixture::ADEM_LANE,
                ActivityType::RsvpYes,
                ['event_id' => $weeklyGoStudy],
            ],
            [
                '2025-01-13 12:30:00',
                UserFixture::ADEM_LANE,
                ActivityType::RsvpYes,
                ['event_id' => $beginnerWorkshop],
            ],
            [
                '2025-01-15 20:00:00',
                UserFixture::ADEM_LANE,
                ActivityType::FollowedUser,
                ['user_id' => $ademLaneId],
            ],
            [
                '2025-01-17 09:00:00',
                UserFixture::ADMIN,
                ActivityType::FollowedUser,
                ['user_id' => $crystalLiuId],
            ],
            [
                '2025-02-01 10:00:00',
                UserFixture::CRYSTAL_LIU,
                ActivityType::Registered,
                null,
            ],
            [
                '2025-02-02 10:00:00',
                UserFixture::CRYSTAL_LIU,
                ActivityType::Login,
                null,
            ],
            [
                '2025-02-03 10:00:00',
                UserFixture::CRYSTAL_LIU,
                ActivityType::ChangedUsername,
                [
                    'old' => 'dalong',
                    'new' => 'xiaolong',
                ],
            ],
            ['2025-03-01 10:00:00', UserFixture::ADMIN, ActivityType::AdminEventCreated,  ['event_id' => $weeklyGoStudy]],
            ['2025-03-02 10:00:00', UserFixture::ADMIN, ActivityType::AdminEventEdited,    ['event_id' => $weeklyGoStudy]],
            ['2025-03-03 10:00:00', UserFixture::ADMIN, ActivityType::AdminEventCancelled, ['event_id' => $berlinTournament]],
            ['2025-03-04 10:00:00', UserFixture::ADMIN, ActivityType::AdminEventDeleted,   ['event_id' => 999, 'event_name' => 'Old Meetup']],
            ['2025-03-05 10:00:00', UserFixture::ADMIN, ActivityType::AdminCmsPageCreated, ['cms_id' => $aboutCmsId, 'cms_slug' => CmsFixture::ABOUT]],
            ['2025-03-06 10:00:00', UserFixture::ADMIN, ActivityType::AdminCmsPageUpdated, ['cms_id' => $aboutCmsId, 'cms_slug' => CmsFixture::ABOUT]],
            ['2025-03-07 10:00:00', UserFixture::ADMIN, ActivityType::AdminCmsPageDeleted, ['cms_id' => 999, 'cms_slug' => 'old-page']],
            ['2025-03-08 10:00:00', UserFixture::ADMIN, ActivityType::AdminMemberApproved, ['user_id' => $ademLaneId]],
            ['2025-03-09 10:00:00', UserFixture::ADMIN, ActivityType::AdminMemberDenied,   ['user_id' => $crystalLiuId]],
            ['2025-03-10 10:00:00', UserFixture::ADMIN, ActivityType::AdminMemberPromoted,  ['user_id' => $ademLaneId]],
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
