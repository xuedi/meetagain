<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuRoutes;
use App\Entity\MenuTranslation;
use App\Entity\MenuType;
use App\Entity\MenuVisibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating menu ... ';
        foreach ($this->getData() as [$location, $visibility, $type, $value, $translations]) {
            if (!isset($priority[$location->value])) {
                $priority[$location->value] = 0;
            }
            $priority[$location->value] += 1;

            $menu = new Menu();
            $menu->setLocation($location);
            $menu->setVisibility($visibility);
            $menu->setPriority($priority[$location->value]);
            $menu->setType($type);
            $menu->setSlug($type === MenuType::Url ? $value : null);
            $menu->setCms($type === MenuType::Cms ? $value : null);
            $menu->setEvent($type === MenuType::Event ? $value : null);
            $menu->setRoute($type === MenuType::Route ? $value : null);

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

    #[\Override]
    public function getDependencies(): array
    {
        return [
            CmsFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            [
                MenuLocation::TopBar,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getReference('cms_' . md5('about'), Cms::class),
                [
                    'de' => 'Ueber uns',
                    'en' => 'About',
                    'cn' => '关于',
                ],
            ],
            [
                MenuLocation::TopBar,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Events,
                [
                    'de' => 'Events',
                    'en' => 'Events',
                    'cn' => '活动',
                ],
            ],
            [
                MenuLocation::TopBar,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Members,
                [
                    'de' => 'Mitglieder',
                    'en' => 'Members',
                    'cn' => '成员',
                ],
            ],
            [
                MenuLocation::BottomCol1,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getReference('cms_' . md5('index'), Cms::class),
                [
                    'de' => 'Startseite',
                    'en' => 'Homepage',
                    'cn' => '主页',
                ],
            ],
            [
                MenuLocation::BottomCol1,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getReference('cms_' . md5('about'), Cms::class),
                [
                    'de' => 'Ueber uns',
                    'en' => 'About',
                    'cn' => '关于',
                ],
            ],
            [
                MenuLocation::BottomCol2,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Events,
                [
                    'de' => 'Events',
                    'en' => 'Events',
                    'cn' => '活动',
                ],
            ],
            [
                MenuLocation::BottomCol2,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Members,
                [
                    'de' => 'Mitglieder',
                    'en' => 'Members',
                    'cn' => '成员',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://meetup.com',
                [
                    'de' => 'meetup.com',
                    'en' => 'meetup.com',
                    'cn' => 'meetup.com',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://instagram.com',
                [
                    'de' => 'Instagram',
                    'en' => 'Instagram',
                    'cn' => 'Instagram',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://tiktok.com',
                [
                    'de' => 'TikTok',
                    'en' => 'TikTok',
                    'cn' => 'TikTok',
                ],
            ],
            [
                MenuLocation::BottomCol4,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getReference('cms_' . md5('imprint'), Cms::class),
                [
                    'de' => 'Impressum',
                    'en' => 'Imprint',
                    'cn' => '印象深刻',
                ],
            ],
            [
                MenuLocation::BottomCol4,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getReference('cms_' . md5('privacy'), Cms::class),
                [
                    'de' => 'Datenschutz',
                    'en' => 'Privacy',
                    'cn' => '数据保护',
                ],
            ],
        ];
    }
}
