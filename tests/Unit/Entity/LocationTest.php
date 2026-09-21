<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class LocationTest extends TestCase
{
    public static function coordinateProvider(): iterable
    {
        yield 'a Berlin pin is accepted' => ['52.520008', '13.404954', []];
        yield 'no coordinates at all are accepted' => [null, null, []];
        yield 'the poles and the date line are accepted' => ['-90', '180', []];
        yield 'a latitude beyond the pole is refused' => ['999.123456', '13.404954', ['latitude']];
        yield 'a swapped pair with the longitude in the latitude slot is refused' => ['113.40', '52.52', ['latitude']];
        yield 'a longitude beyond the date line is refused' => ['52.52', '-180.5', ['longitude']];
        yield 'hand-typed junk is refused' => ['north', 'east', ['latitude', 'longitude']];
    }

    #[DataProvider('coordinateProvider')]
    public function testCoordinatesAreRangeChecked(?string $latitude, ?string $longitude, array $expectedViolations): void
    {
        // Arrange
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $location = new Location();
        $location->setLatitude($latitude);
        $location->setLongitude($longitude);

        // Act
        $violations = $validator->validate($location);

        // Assert
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        $paths = array_values(array_unique($paths));
        sort($paths);
        static::assertSame($expectedViolations, $paths);
    }
}
