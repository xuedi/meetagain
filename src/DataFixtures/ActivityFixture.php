<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Activity\Messages\AdminCmsPageCreated;
use App\Activity\Messages\AdminCmsPageDeleted;
use App\Activity\Messages\AdminCmsPageUpdated;
use App\Activity\Messages\AdminEventCancelled;
use App\Activity\Messages\AdminEventCreated;
use App\Activity\Messages\AdminEventDeleted;
use App\Activity\Messages\AdminEventEdited;
use App\Activity\Messages\AdminMemberApproved;
use App\Activity\Messages\AdminMemberDenied;
use App\Activity\Messages\AdminMemberPromoted;
use App\Activity\Messages\ChangedUsername;
use App\Activity\Messages\FollowedUser;
use App\Activity\Messages\Login;
use App\Activity\Messages\Registered;
use App\Activity\Messages\RsvpYes;
use App\Entity\Activity;
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
                Registered::TYPE,
                null,
            ],
            [
                '2025-01-02 10:00:00',
                UserFixture::ADMIN,
                Login::TYPE,
                null,
            ],
            [
                '2025-01-03 10:00:00',
                UserFixture::ADMIN,
                RsvpYes::TYPE,
                ['event_id' => $weeklyGoStudy],
            ],
            [
                '2025-01-04 10:00:00',
                UserFixture::ADMIN,
                RsvpYes::TYPE,
                ['event_id' => $berlinTournament],
            ],
            [
                '2025-01-04 12:30:00',
                UserFixture::ADMIN,
                RsvpYes::TYPE,
                ['event_id' => $beginnerWorkshop],
            ],
            [
                '2025-01-11 10:00:00',
                UserFixture::ADEM_LANE,
                Registered::TYPE,
                null,
            ],
            [
                '2025-01-12 10:00:00',
                UserFixture::ADEM_LANE,
                Login::TYPE,
                null,
            ],
            [
                '2025-01-13 10:00:00',
                UserFixture::ADEM_LANE,
                RsvpYes::TYPE,
                ['event_id' => $weeklyGoStudy],
            ],
            [
                '2025-01-13 12:30:00',
                UserFixture::ADEM_LANE,
                RsvpYes::TYPE,
                ['event_id' => $beginnerWorkshop],
            ],
            [
                '2025-01-15 20:00:00',
                UserFixture::ADEM_LANE,
                FollowedUser::TYPE,
                ['user_id' => $ademLaneId],
            ],
            [
                '2025-01-17 09:00:00',
                UserFixture::ADMIN,
                FollowedUser::TYPE,
                ['user_id' => $crystalLiuId],
            ],
            [
                '2025-02-01 10:00:00',
                UserFixture::CRYSTAL_LIU,
                Registered::TYPE,
                null,
            ],
            [
                '2025-02-02 10:00:00',
                UserFixture::CRYSTAL_LIU,
                Login::TYPE,
                null,
            ],
            [
                '2025-02-03 10:00:00',
                UserFixture::CRYSTAL_LIU,
                ChangedUsername::TYPE,
                [
                    'old' => 'dalong',
                    'new' => 'xiaolong',
                ],
            ],
            ['2025-03-01 10:00:00', UserFixture::ADMIN, AdminEventCreated::TYPE,  ['event_id' => $weeklyGoStudy]],
            ['2025-03-02 10:00:00', UserFixture::ADMIN, AdminEventEdited::TYPE,    ['event_id' => $weeklyGoStudy]],
            ['2025-03-03 10:00:00', UserFixture::ADMIN, AdminEventCancelled::TYPE, ['event_id' => $berlinTournament]],
            ['2025-03-04 10:00:00', UserFixture::ADMIN, AdminEventDeleted::TYPE,   ['event_id' => 999, 'event_name' => 'Old Meetup']],
            ['2025-03-05 10:00:00', UserFixture::ADMIN, AdminCmsPageCreated::TYPE, ['cms_id' => $aboutCmsId, 'cms_slug' => CmsFixture::ABOUT]],
            ['2025-03-06 10:00:00', UserFixture::ADMIN, AdminCmsPageUpdated::TYPE, ['cms_id' => $aboutCmsId, 'cms_slug' => CmsFixture::ABOUT]],
            ['2025-03-07 10:00:00', UserFixture::ADMIN, AdminCmsPageDeleted::TYPE, ['cms_id' => 999, 'cms_slug' => 'old-page']],
            ['2025-03-08 10:00:00', UserFixture::ADMIN, AdminMemberApproved::TYPE, ['user_id' => $ademLaneId]],
            ['2025-03-09 10:00:00', UserFixture::ADMIN, AdminMemberDenied::TYPE,   ['user_id' => $crystalLiuId]],
            ['2025-03-10 10:00:00', UserFixture::ADMIN, AdminMemberPromoted::TYPE,  ['user_id' => $ademLaneId]],
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
