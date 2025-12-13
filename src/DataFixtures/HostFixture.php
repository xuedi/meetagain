<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Host;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class HostFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string ADMIN = 'Admin';
    public const string ADEM = 'Adem';
    public const string CRYSTAL = 'Crystal';
    public const string JESSIE = 'Jessie';
    public const string MOLLIE = 'Mollie';

    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$name, $user]) {
            $host = new Host();
            $host->setName($name);
            if ($user) {
                $host->setUser($this->getRefUser($user));
            }

            $manager->persist($host);
            $this->addRefHost($name, $host);
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
            [self::ADMIN, UserFixture::ADMIN],
            [self::ADEM, UserFixture::ADEM_LANE],
            [self::CRYSTAL, UserFixture::CRYSTAL_LIU],
            [self::JESSIE, UserFixture::JESSIE_MEYTON],
            [self::MOLLIE, UserFixture::MOLLIE_HALL],
        ];
    }
}
