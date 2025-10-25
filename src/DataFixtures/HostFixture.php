<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Host;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class HostFixture extends Fixture implements DependentFixtureInterface
{
    public const string ADMIN = 'Admin';
    public const string ADEM = 'Adem';
    public const string CRYSTAL = 'Crystal';
    public const string JESSIE = 'Jessie';
    public const string MOLLIE = 'Mollie';

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating hosts ... ';
        foreach ($this->getData() as [$name, $user]) {
            $host = new Host();
            $host->setName($name);
            if ($user) {
                $host->setUser($this->getReference('UserFixture::' . md5((string)$user), User::class));
            }

            $manager->persist($host);

            $this->addReference('HostFixture::' . md5((string)$name), $host);
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
            [self::ADMIN, UserFixture::ADMIN],
            [self::ADEM, UserFixture::ADEM_LANE],
            [self::CRYSTAL, UserFixture::CRYSTAL_LIU],
            [self::JESSIE, UserFixture::JESSIE_MEYTON],
            [self::MOLLIE, UserFixture::MOLLIE_HALL],
        ];
    }
}
