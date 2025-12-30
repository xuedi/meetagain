<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuRoutes;
use App\Entity\MenuTranslation;
use App\Entity\MenuType;
use App\Entity\MenuVisibility;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuFixture extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$location, $visibility, $type, $value, $translations]) {
            if (!isset($priority[$location->value])) {
                $priority[$location->value] = 0;
            }
            ++$priority[$location->value];

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
        $this->stop();
    }

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
                $this->getRefCms(CmsFixture::ABOUT),
                [
                    LanguageFixture::GERMAN => 'Ueber uns',
                    LanguageFixture::ENGLISH => 'About',
                    LanguageFixture::CHINESE => '关于',
                ],
            ],
            [
                MenuLocation::TopBar,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Events,
                [
                    LanguageFixture::GERMAN => 'Events',
                    LanguageFixture::ENGLISH => 'Events',
                    LanguageFixture::CHINESE => '活动',
                ],
            ],
            [
                MenuLocation::TopBar,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Members,
                [
                    LanguageFixture::GERMAN => 'Mitglieder',
                    LanguageFixture::ENGLISH => 'Members',
                    LanguageFixture::CHINESE => '成员',
                ],
            ],
            [
                MenuLocation::BottomCol1,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getRefCms(CmsFixture::INDEX),
                [
                    LanguageFixture::GERMAN => 'Startseite',
                    LanguageFixture::ENGLISH => 'Homepage',
                    LanguageFixture::CHINESE => '主页',
                ],
            ],
            [
                MenuLocation::BottomCol1,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getRefCms(CmsFixture::ABOUT),
                [
                    LanguageFixture::GERMAN => 'Ueber uns',
                    LanguageFixture::ENGLISH => 'About',
                    LanguageFixture::CHINESE => '关于',
                ],
            ],
            [
                MenuLocation::BottomCol2,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Events,
                [
                    LanguageFixture::GERMAN => 'Events',
                    LanguageFixture::ENGLISH => 'Events',
                    LanguageFixture::CHINESE => '活动',
                ],
            ],
            [
                MenuLocation::BottomCol2,
                MenuVisibility::Everyone,
                MenuType::Route,
                MenuRoutes::Members,
                [
                    LanguageFixture::GERMAN => 'Mitglieder',
                    LanguageFixture::ENGLISH => 'Members',
                    LanguageFixture::CHINESE => '成员',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://meetup.com',
                [
                    LanguageFixture::GERMAN => 'meetup.com',
                    LanguageFixture::ENGLISH => 'meetup.com',
                    LanguageFixture::CHINESE => 'meetup.com',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://instagram.com',
                [
                    LanguageFixture::GERMAN => 'Instagram',
                    LanguageFixture::ENGLISH => 'Instagram',
                    LanguageFixture::CHINESE => 'Instagram',
                ],
            ],
            [
                MenuLocation::BottomCol3,
                MenuVisibility::Everyone,
                MenuType::Url,
                'https://tiktok.com',
                [
                    LanguageFixture::GERMAN => 'TikTok',
                    LanguageFixture::ENGLISH => 'TikTok',
                    LanguageFixture::CHINESE => 'TikTok',
                ],
            ],
            [
                MenuLocation::BottomCol4,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getRefCms(CmsFixture::IMPRINT),
                [
                    LanguageFixture::GERMAN => 'Impressum',
                    LanguageFixture::ENGLISH => 'Imprint',
                    LanguageFixture::CHINESE => '印象深刻',
                ],
            ],
            [
                MenuLocation::BottomCol4,
                MenuVisibility::Everyone,
                MenuType::Cms,
                $this->getRefCms(CmsFixture::PRIVACY),
                [
                    LanguageFixture::GERMAN => 'Datenschutz',
                    LanguageFixture::ENGLISH => 'Privacy',
                    LanguageFixture::CHINESE => '数据保护',
                ],
            ],
            [
                MenuLocation::BottomCol2,
                MenuVisibility::User,
                MenuType::Route,
                MenuRoutes::Translation,
                [
                    LanguageFixture::GERMAN => 'Übersetzen',
                    LanguageFixture::ENGLISH => 'Translate',
                    LanguageFixture::CHINESE => '翻译',
                ],
            ],
        ];
    }
}
