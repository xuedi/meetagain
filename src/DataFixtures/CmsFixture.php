<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Enum\MenuLocation;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CmsFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string INDEX = 'index';
    public const string PRIVACY = 'privacy';
    public const string ABOUT = 'about';
    public const string RULES = 'rules';
    public const string IMPRINT = 'imprint';
    public const string ANNOUNCEMENT = '2026-01-new-website';

    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$slug, $menuLocations]) {
            $cms = new Cms();
            $cms->setSlug($slug);
            $cms->setCreatedAt(new DateTimeImmutable());
            $cms->setCreatedBy($this->getRefUser(UserFixture::ADMIN));
            $cms->setPublished(true);
            $cms->setLocked(in_array($slug, [self::PRIVACY, self::IMPRINT], true));
            if ($menuLocations !== null) {
                foreach ($menuLocations as $value) {
                    $location = MenuLocation::from($value);
                    $menuLocation = new CmsMenuLocation();
                    $menuLocation->setCms($cms);
                    $menuLocation->setLocation($location);
                    $cms->addMenuLocation($menuLocation);
                }
            }

            $manager->persist($cms);
            $this->addRefCms($slug, $cms);
        }
        $manager->flush();

        $this->createTitles($manager);
        $this->createLinkNames($manager);

        $this->stop();
    }

    private function createTitles(ObjectManager $manager): void
    {
        $titles = [
            [self::INDEX, 'en', 'MeetAgain - Event Management'],
            [self::INDEX, 'de', 'MeetAgain - Event-Management'],
            [self::INDEX, 'zh', 'MeetAgain - 活动管理'],
            [self::PRIVACY, 'en', 'Privacy Policy'],
            [self::PRIVACY, 'de', 'Datenschutzerklärung'],
            [self::PRIVACY, 'zh', '隐私政策'],
            [self::ABOUT, 'en', 'About Us'],
            [self::ABOUT, 'de', 'Über uns'],
            [self::ABOUT, 'zh', '关于我们'],
            [self::RULES, 'en', 'Community Rules'],
            [self::RULES, 'de', 'Community-Regeln'],
            [self::RULES, 'zh', '社区规则'],
            [self::IMPRINT, 'en', 'Imprint'],
            [self::IMPRINT, 'de', 'Impressum'],
            [self::IMPRINT, 'zh', '版本说明'],
            [self::ANNOUNCEMENT, 'en', 'New Website Version Released!'],
            [self::ANNOUNCEMENT, 'de', 'Neue Website-Version veröffentlicht!'],
            [self::ANNOUNCEMENT, 'zh', '新版本网站发布！'],
        ];

        foreach ($titles as [$slug, $language, $titleText]) {
            $title = new CmsTitle();
            $title->setCms($this->getRefCms($slug));
            $title->setLanguage($language);
            $title->setTitle($titleText);
            $manager->persist($title);
        }

        $manager->flush();
    }

    private function createLinkNames(ObjectManager $manager): void
    {
        $linkNames = [
            [self::INDEX, 'en', 'Home'],
            [self::INDEX, 'de', 'Startseite'],
            [self::INDEX, 'zh', '首页'],
            [self::PRIVACY, 'en', 'Privacy'],
            [self::PRIVACY, 'de', 'Datenschutz'],
            [self::PRIVACY, 'zh', '隐私'],
            [self::ABOUT, 'en', 'About'],
            [self::ABOUT, 'de', 'Über uns'],
            [self::ABOUT, 'zh', '关于'],
            [self::RULES, 'en', 'Rules'],
            [self::RULES, 'de', 'Regeln'],
            [self::RULES, 'zh', '规则'],
            [self::IMPRINT, 'en', 'Imprint'],
            [self::IMPRINT, 'de', 'Impressum'],
            [self::IMPRINT, 'zh', '版本说明'],
            [self::ANNOUNCEMENT, 'en', 'News'],
            [self::ANNOUNCEMENT, 'de', 'Neuigkeiten'],
            [self::ANNOUNCEMENT, 'zh', '新闻'],
        ];

        foreach ($linkNames as [$slug, $language, $nameText]) {
            $linkName = new CmsLinkName();
            $linkName->setCms($this->getRefCms($slug));
            $linkName->setLanguage($language);
            $linkName->setName($nameText);
            $manager->persist($linkName);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            [self::INDEX, [MenuLocation::BottomCol1->value]],
            [self::PRIVACY, [MenuLocation::BottomCol4->value]],
            [self::ABOUT, [MenuLocation::TopBar->value, MenuLocation::BottomCol1->value]],
            [self::RULES, null],
            [self::IMPRINT, [MenuLocation::BottomCol4->value]],
            [self::ANNOUNCEMENT, null],
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
