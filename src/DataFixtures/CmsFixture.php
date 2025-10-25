<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CmsFixture extends Fixture implements DependentFixtureInterface
{
    public const string INDEX = 'index';
    public const string PRIVACY = 'privacy';
    public const string ABOUT = 'about';
    public const string IMPRINT = 'imprint';

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating cms pages ... ';
        foreach ($this->getData() as [$slug]) {
            $cms = new Cms();
            $cms->setSlug($slug);
            $cms->setCreatedAt(new DateTimeImmutable());
            $cms->setCreatedBy($this->getReference('UserFixture::' . md5('import'), User::class));
            $cms->setPublished(true);

            $manager->persist($cms);
            $this->addReference('CmsFixture::' . md5($slug), $cms);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
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
            [self::INDEX],
            [self::PRIVACY],
            [self::ABOUT],
            [self::IMPRINT],
        ];
    }
}
