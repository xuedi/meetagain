<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Config;
use App\Entity\ConfigType;
use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuTranslation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MenuFixture extends Fixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating menu ... ';
        foreach ($this->getData() as [$slug, $location, $translations]) {
            $menu = new Menu();
            $menu->setSlug($slug);
            $menu->setLocation($location);

            $manager->persist($menu);

            foreach ($translations as $locale => $name) {
                $translation = new MenuTranslation();
                $translation->setMenu($menu);
                $translation->setLanguage($locale);
                $translation->setName($name);

                $manager->persist($translation);
                $menu->addTranslation($translation);
            }
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    private function getData(): array
    {
        return [
            [
                'about',
                MenuLocation::TopBar,
                [
                    'de' => 'Ueber uns',
                    'en' => 'About',
                    'cn' => '关于',
                ],
            ],
            [
                'events',
                MenuLocation::TopBar,
                [
                    'de' => 'Events',
                    'en' => 'Events',
                    'cn' => '活动',
                ],
            ],
            [
                'members',
                MenuLocation::TopBar,
                [
                    'de' => 'Mitglieder',
                    'en' => 'Members',
                    'cn' => '成员',
                ],
            ],
            [
                'index',
                MenuLocation::BottomCol1,
                [
                    'de' => 'Startseite',
                    'en' => 'Homepage',
                    'cn' => '主页',
                ],
            ],
            [
                'about',
                MenuLocation::BottomCol1,
                [
                    'de' => 'Ueber uns',
                    'en' => 'About',
                    'cn' => '关于',
                ],
            ],
            [
                'events',
                MenuLocation::BottomCol2,
                [
                    'de' => 'Events',
                    'en' => 'Events',
                    'cn' => '活动',
                ],
            ],
            [
                'members',
                MenuLocation::BottomCol2,
                [
                    'de' => 'Mitglieder',
                    'en' => 'Members',
                    'cn' => '成员',
                ],
            ],
            [
                'external',
                MenuLocation::BottomCol3,
                [
                    'de' => 'meetup.com',
                    'en' => 'meetup.com',
                    'cn' => 'meetup.com',
                ],
            ],
            [
                'instagram',
                MenuLocation::BottomCol3,
                [
                    'de' => 'Instagram',
                    'en' => 'Instagram',
                    'cn' => 'Instagram',
                ],
            ],
            [
                'tiktok',
                MenuLocation::BottomCol3,
                [
                    'de' => 'TikTok',
                    'en' => 'TikTok',
                    'cn' => 'TikTok',
                ],
            ],
            [
                'imprint',
                MenuLocation::BottomCol4,
                [
                    'de' => 'Impressum',
                    'en' => 'Imprint',
                    'cn' => '印象深刻',
                ],
            ],
            [
                'privacy',
                MenuLocation::BottomCol4,
                [
                    'de' => 'Datenschutz',
                    'en' => 'Privacy',
                    'cn' => '数据保护',
                ],
            ],
        ];
    }
}
