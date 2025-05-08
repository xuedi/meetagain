<?php declare(strict_types=1);

namespace App\TestsUnit\Entity;

use App\Entity\Location;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class LocationTest extends TestCase
{
    private Location $location;

    protected function setUp(): void
    {
        $this->location = new Location();
    }

    public function testInitialState(): void
    {
        self::assertNull($this->location->getId());
        self::assertNull($this->location->getName());
        self::assertNull($this->location->getDescription());
        self::assertNull($this->location->getStreet());
        self::assertNull($this->location->getCity());
        self::assertNull($this->location->getPostcode());
        self::assertNull($this->location->getUser());
        self::assertNull($this->location->getCreatedAt());
        self::assertNull($this->location->getLongitude());
        self::assertNull($this->location->getLatitude());
    }

    public function testNameGetterAndSetter(): void
    {
        $name = 'Test Location';
        $result = $this->location->setName($name);

        self::assertSame($this->location, $result);
        self::assertSame($name, $this->location->getName());
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $description = 'Test Description';
        $result = $this->location->setDescription($description);

        self::assertSame($this->location, $result);
        self::assertSame($description, $this->location->getDescription());
    }

    public function testStreetGetterAndSetter(): void
    {
        $street = 'Test Street 123';
        $result = $this->location->setStreet($street);

        self::assertSame($this->location, $result);
        self::assertSame($street, $this->location->getStreet());
    }

    public function testCityGetterAndSetter(): void
    {
        $city = 'Test City';
        $result = $this->location->setCity($city);

        self::assertSame($this->location, $result);
        self::assertSame($city, $this->location->getCity());
    }

    public function testPostcodeGetterAndSetter(): void
    {
        $postcode = '12345';
        $result = $this->location->setPostcode($postcode);

        self::assertSame($this->location, $result);
        self::assertSame($postcode, $this->location->getPostcode());
    }

    public function testUserGetterAndSetter(): void
    {
        $user = new User();
        $result = $this->location->setUser($user);

        self::assertSame($this->location, $result);
        self::assertSame($user, $this->location->getUser());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $createdAt = new \DateTimeImmutable();
        $result = $this->location->setCreatedAt($createdAt);

        self::assertSame($this->location, $result);
        self::assertSame($createdAt, $this->location->getCreatedAt());
    }

    public function testLongitudeGetterAndSetter(): void
    {
        $longitude = '123.456';
        $result = $this->location->setLongitude($longitude);

        self::assertSame($this->location, $result);
        self::assertSame($longitude, $this->location->getLongitude());

        // Test nullable
        $this->location->setLongitude(null);
        self::assertNull($this->location->getLongitude());
    }

    public function testLatitudeGetterAndSetter(): void
    {
        $latitude = '78.901';
        $result = $this->location->setLatitude($latitude);

        self::assertSame($this->location, $result);
        self::assertSame($latitude, $this->location->getLatitude());

        // Test nullable
        $this->location->setLatitude(null);
        self::assertNull($this->location->getLatitude());
    }

    public function testFluentInterface(): void
    {
        $user = new User();
        $createdAt = new \DateTimeImmutable();

        $result = $this->location
            ->setName('Test Location')
            ->setDescription('Test Description')
            ->setStreet('Test Street')
            ->setCity('Test City')
            ->setPostcode('12345')
            ->setUser($user)
            ->setCreatedAt($createdAt)
            ->setLongitude('123.456')
            ->setLatitude('78.901');

        self::assertSame($this->location, $result);
    }
}
