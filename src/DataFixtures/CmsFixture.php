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
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating cms pages ... ';
        foreach ($this->getData() as [$slug]) {
            $cms = new Cms();
            $cms->setSlug($slug);
            $cms->setCreatedAt(new DateTimeImmutable());
            $cms->setCreatedBy($this->getReference('user_' . md5('import'), User::class));
            $cms->setPublished(true);

            $manager->persist($cms);
            $this->addReference('cms_' . md5((string) $slug), $cms);
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
            ['imprint'],
            ['privacy'],
            ['about'],
            ['index'],
        ];
    }
}
