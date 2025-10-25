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
    public const string CAFE_LINDENHOF = 'Cafe Lindenhof';
    public const string KAFFEE_WERK = 'Kaffeewerk Mitte';
    public const string SPREE_BLICK = 'Spreeblick Cafe';
    public const string GRUNEWALD_CAMPING = 'Grunewald Camping';
    public const string ICC_BERLIN = 'ICC Berlin';
    public const string AIRPORT_BER = 'BER Flughafen';

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
            $location->setUser($this->getReference('UserFixture::' . md5('import'), User::class));
            $location->setLongitude($long);
            $location->setLatitude($lat);
            $location->setCreatedAt(new DateTimeImmutable());

            $manager->persist($location);

            $this->addReference('LocationFixture::' . md5((string)$name), $location);
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
            [self::KAFFEE_WERK, 'Invalidenstraße 100', 'Berlin', '10115', '', '52.5321', '13.3799'],
            [self::CAFE_LINDENHOF, 'Lindenstraße 45', 'Berlin', '10969', '', '52.5035', '13.4052'],
            [self::SPREE_BLICK, 'Friedrichstraße 210a', 'Berlin', '10117', '', '52.5106', '13.3880'],
            [self::GRUNEWALD_CAMPING, 'Im Gruenen 3', 'Berlin', '10137', '', '52.44934', '13.1687'],
            [self::ICC_BERLIN, 'Messedamm 22', 'Berlin', '14055', '', '52.5070', '13.2729'],
            [self::AIRPORT_BER, 'Willy-Brandt-Platz', 'Schönefeld', '12529', '', '52.3667', '13.5033'],
        ];
    }
}
