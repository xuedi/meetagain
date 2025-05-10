<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CmsFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$slug]) {
            $cms = new Cms();
            $cms->setSlug($slug);
            $cms->setCreatedAt(new DateTimeImmutable());
            $cms->setCreatedBy($this->getReference('user_' . md5('import')));
            $cms->setPublished(true);

            $manager->persist($cms);
            $this->addReference('cms_' . md5((string) $slug), $cms);
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
            ['imprint'],
            ['privacy'],
            ['about'],
            ['index'],
        ];
    }
}
