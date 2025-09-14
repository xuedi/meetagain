<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Location;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LocationFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating locations ... ';
        foreach ($this->getData() as [$name, $street, $city, $postcode, $description, $long, $lat]) {
            $location = new Location();
            $location->setName($name);
            $location->setStreet($street);
            $location->setCity($city);
            $location->setPostcode($postcode);
            $location->setDescription($description);
            $location->setUser($this->getReference('user_' . md5('import'), User::class));
            $location->setLongitude($long);
            $location->setLatitude($lat);
            $location->setCreatedAt(new DateTimeImmutable());

            $manager->persist($location);

            $this->addReference('location_' . md5((string) $name), $location);
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
            [
                'Garten der Welt',
                'Eisenacher Str. 99',
                'Berlin',
                '12685',
                'BUS 195 stops right next to the entrance where we will meet. If you miss our meeting time, call me on (redacted) and I\'ll try to guide you to us.',
                '52.5400197151975',
                '13.576645889761176',
            ],
            ['St. Oberholz', 'Rosenthaler Straße 72', 'Berlin', '10119', '', '52.52953960746029', '13.401831300250606'],
            ['Grand Tang', 'Pestalozzistr. 37', 'Berlin', '10627', '', '52.537911306794754', '13.423009677540934'],
            ['Lao Xiang', 'Wichert Str 43', 'Berlin', '10439', '', '52.54645961826611', '13.427076146882994'],
            ['Volksbar', 'Rosa-Luxemburg-Straße 39', 'Berlin', '10178', '', '52.52687270379576', '13.410462405310192'],
            [
                'Himmelbeet',
                ' Gartenstraße / Ecke Grenzstaße',
                'Berlin',
                '13355',
                '',
                '52.54131106618176',
                '13.378411012298372',
            ],
        ];
    }
}
