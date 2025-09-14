<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Host;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class HostFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating hosts ... ';
        foreach ($this->getData() as [$name, $user]) {
            $host = new Host();
            $host->setName($name);
            if ($user) {
                $host->setUser($this->getReference('user_' . md5((string) $user), User::class));
            }

            $manager->persist($host);

            $this->addReference('host_' . md5((string) $name), $host);
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
            ['admin',       'admin'],
            ['Adem Lane',   'Adem Lane'],
            ['Crystal Liu', 'Crystal Liu'],
        ];
    }
}
