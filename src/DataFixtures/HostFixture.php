<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Host;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class HostFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$name, $user]) {
            $host = new Host();
            $host->setName($name);
            if ($user) {
                $host->setUser($this->getReference('user_' . md5((string) $user)));
            }

            $manager->persist($host);

            $this->addReference('host_' . md5((string)$name), $host);
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
            ['雪地', 'xuedi'],
            ['易木', 'yimu'],
            ['xiaolong', null],
        ];
    }
}
