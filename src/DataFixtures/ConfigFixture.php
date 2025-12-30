<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Config;
use App\Entity\ConfigType;
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
                'localhost',
                ConfigType::String,
            ],
            [
                'website_host',
                'https://localhost',
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
            // Theme colors (with WCAG AA compliant defaults for grey text)
            ['color_primary', '#00d1b2', ConfigType::String],
            ['color_link', '#485fc7', ConfigType::String],
            ['color_info', '#3e8ed0', ConfigType::String],
            ['color_success', '#48c78e', ConfigType::String],
            ['color_warning', '#ffe08a', ConfigType::String],
            ['color_danger', '#f14668', ConfigType::String],
            ['color_text_grey', '#767676', ConfigType::String],
            ['color_text_grey_light', '#959595', ConfigType::String],
        ];
    }
}
