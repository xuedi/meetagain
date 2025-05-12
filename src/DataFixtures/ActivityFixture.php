<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\User;
use App\Entity\UserActivity;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ActivityFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$time, $userName, $type, $meta]) {
            $activity = new Activity();
            $activity->setUser($this->getReference('user_' . md5((string) $userName), User::class));
            $activity->setCreatedAt(new DateTimeImmutable($time));
            $activity->setVisible(true);
            $activity->setMessage('');
            $activity->setType($type);
            $activity->setMeta($meta);

            $manager->persist($activity);
        }
        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            ['2025-01-01 10:00:00', 'xuedi', UserActivity::Registered, null],
            ['2025-01-02 10:00:00', 'xuedi', UserActivity::Login, null],
            ['2025-01-03 10:00:00', 'xuedi', UserActivity::RsvpYes, ['event_id' => 1]],
            ['2025-01-04 10:00:00', 'xuedi', UserActivity::RsvpYes, ['event_id' => 2]],
            ['2025-01-04 12:30:00', 'xuedi', UserActivity::RsvpYes, ['event_id' => 6]],
            ['2025-01-11 10:00:00', 'Adem Lane', UserActivity::Registered, null],
            ['2025-01-12 10:00:00', 'Adem Lane', UserActivity::Login, null],
            ['2025-01-13 10:00:00', 'Adem Lane', UserActivity::RsvpYes, ['event_id' => 1]],
            ['2025-01-13 12:30:00', 'Adem Lane', UserActivity::RsvpYes, ['event_id' => 6]],
            ['2025-01-15 20:00:00', 'Adem Lane', UserActivity::FollowedUser, ['user_id' => 2]],
            ['2025-01-17 09:00:00', 'xuedi', UserActivity::FollowedUser, ['user_id' => 3]],
            ['2025-02-01 10:00:00', 'Crystal Liu', UserActivity::Registered, null],
            ['2025-02-02 10:00:00', 'Crystal Liu', UserActivity::Login, null],
            ['2025-02-03 10:00:00', 'Crystal Liu', UserActivity::ChangedUsername, ['old' => 'dalong', 'new' => 'xiaolong']],
        ];
    }
}
