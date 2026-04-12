<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Config;
use App\Enum\ConfigType;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ConfigFixture extends AbstractFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$name, $value, $type]) {
            $user = new Config();
            $user->setName($name);
            $user->setValue($value);
            $user->setType($type);

            $manager->persist($user);
        }
        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            SystemUserFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['install'];
    }

    private function getData(): array
    {
        return [
            [
                'automatic_registration',
                'false',
                ConfigType::Boolean,
            ],
            [
                'show_frontpage',
                'false',
                ConfigType::Boolean,
            ],
            [
                'send_rsvp_notifications',
                'true',
                ConfigType::Boolean,
            ],
            [
                'send_admin_notification',
                'true',
                ConfigType::Boolean,
            ],
            [
                'email_delivery_sync_enabled',
                'false',
                ConfigType::Boolean,
            ],
            [
                'send_event_reminders',
                'true',
                ConfigType::Boolean,
            ],
            [
                'send_upcoming_digest',
                'true',
                ConfigType::Boolean,
            ],
            [
                'email_sender_mail',
                'email@localhost',
                ConfigType::String,
            ],
            [
                'email_sender_name',
                'localhost',
                ConfigType::String,
            ],
            [
                'website_url',
                'meetagain.local',
                ConfigType::String,
            ],
            [
                'website_host',
                'https://meetagain.local',
                ConfigType::String,
            ],
            [
                'system_user_id',
                (string) $this->getRefUser(SystemUserFixture::IMPORT)->getId(),
                ConfigType::Integer,
            ],
            [
                'date_format',
                'Y-m-d H:i',
                ConfigType::String,
            ],
        ];
    }
}
