<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\MenuLocation;
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
            $cms->setMenuLocations($menuLocations);

            $manager->persist($cms);
            $this->addRefCms($slug, $cms);
        }
        $manager->flush();
        $this->stop();
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
