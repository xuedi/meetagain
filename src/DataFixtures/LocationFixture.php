<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Location;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LocationFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string WEIQI_CAFE = 'Weiqi Cafe Berlin';
    public const string COMMUNITY_CENTER = 'Community Center Mitte';
    public const string OSTBAHNHOF_STUDY = 'Ostbahnhof Study Room';
    public const string GRUNEWALD_CAMPING = 'Grunewald Camping';
    public const string ONLINE_PLATFORM = 'Online Platform';
    public const string VOLKSPARK_FRIEDRICHSHAIN = 'Volkspark Friedrichshain';

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $importUser = $this->getRefUser(UserFixture::ADMIN);
        foreach ($this->getData() as [$name, $street, $city, $postcode, $description, $long, $lat]) {
            $location = new Location();
            $location->setName($name);
            $location->setStreet($street);
            $location->setCity($city);
            $location->setPostcode($postcode);
            $location->setDescription($description);
            $location->setUser($importUser);
            $location->setLongitude($long);
            $location->setLatitude($lat);
            $location->setCreatedAt(new DateTimeImmutable());

            $manager->persist($location);

            $this->addRefLocation($name, $location);
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
            [
                self::WEIQI_CAFE,
                'Brunnenstraße 181',
                'Berlin',
                '10119',
                'Dedicated Go cafe with 10 traditional goban tables and a cozy atmosphere. Perfect for casual games and study sessions.',
                '52.5321',
                '13.3799',
            ],
            [
                self::COMMUNITY_CENTER,
                'Invalidenstraße 50',
                'Berlin',
                '10115',
                'Large community center with spacious hall, projector, and seating for 100 people. Ideal for tournaments and lectures.',
                '52.5035',
                '13.4052',
            ],
            [
                self::OSTBAHNHOF_STUDY,
                'Koppenstraße 3',
                'Berlin',
                '10243',
                'Small meeting room near Ostbahnhof station. Quiet space for focused study groups, capacity 12 people.',
                '52.5106',
                '13.3880',
            ],
            [
                self::GRUNEWALD_CAMPING,
                'Am Grunewaldsee 1',
                'Berlin',
                '14193',
                'Nature retreat location in Grunewald forest. Perfect for weekend intensive training sessions away from the city.',
                '52.4693',
                '13.2687',
            ],
            [
                self::ONLINE_PLATFORM,
                '',
                '',
                '',
                'Online Go Server (OGS) - Virtual location for remote events and teaching games.',
                '0',
                '0',
            ],
            [
                self::VOLKSPARK_FRIEDRICHSHAIN,
                'Friedenstraße',
                'Berlin',
                '10249',
                'Beautiful outdoor park location for summer Go sessions. Bring your own boards and enjoy nature!',
                '52.5267',
                '13.4333',
            ],
        ];
    }
}
