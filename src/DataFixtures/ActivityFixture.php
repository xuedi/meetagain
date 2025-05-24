<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\User;
use App\Entity\ActivityType;
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
            ['2025-01-01 10:00:00', 'xuedi', ActivityType::Registered, null],
            ['2025-01-02 10:00:00', 'xuedi', ActivityType::Login, null],
            ['2025-01-03 10:00:00', 'xuedi', ActivityType::RsvpYes, ['event_id' => 1]],
            ['2025-01-04 10:00:00', 'xuedi', ActivityType::RsvpYes, ['event_id' => 2]],
            ['2025-01-04 12:30:00', 'xuedi', ActivityType::RsvpYes, ['event_id' => 6]],
            ['2025-01-11 10:00:00', 'Adem Lane', ActivityType::Registered, null],
            ['2025-01-12 10:00:00', 'Adem Lane', ActivityType::Login, null],
            ['2025-01-13 10:00:00', 'Adem Lane', ActivityType::RsvpYes, ['event_id' => 1]],
            ['2025-01-13 12:30:00', 'Adem Lane', ActivityType::RsvpYes, ['event_id' => 6]],
            ['2025-01-15 20:00:00', 'Adem Lane', ActivityType::FollowedUser, ['user_id' => 2]],
            ['2025-01-17 09:00:00', 'xuedi', ActivityType::FollowedUser, ['user_id' => 3]],
            ['2025-02-01 10:00:00', 'Crystal Liu', ActivityType::Registered, null],
            ['2025-02-02 10:00:00', 'Crystal Liu', ActivityType::Login, null],
            ['2025-02-03 10:00:00', 'Crystal Liu', ActivityType::ChangedUsername, ['old' => 'dalong', 'new' => 'xiaolong']],
        ];
    }
}
