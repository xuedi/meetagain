<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\SwapReversedVenueCoordinates;
use App\Entity\Location;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SwapReversedVenueCoordinatesTest extends TestCase
{
    public static function coordinateProvider(): iterable
    {
        yield 'a latitude beyond the pole is swapped back' => ['113.4050', '52.5200', '52.5200', '113.4050', 0];
        yield 'an in-range pair that looks reversed is only logged' => ['13.4050', '52.5200', '13.4050', '52.5200', 1];
        yield 'a correct Berlin pin is left alone' => ['52.5200', '13.4050', '52.5200', '13.4050', 0];
        yield 'a venue without coordinates is left alone' => [null, null, null, null, 0];
        yield 'a pair out of range on both axes is left alone' => ['999', '999', '999', '999', 0];
    }

    #[DataProvider('coordinateProvider')]
    public function testCorrectsOnlyUnambiguouslyReversedPairs(
        ?string $latitude,
        ?string $longitude,
        ?string $expectedLatitude,
        ?string $expectedLongitude,
        int $expectedWarnings,
    ): void {
        // Arrange
        $venue = new Location();
        $venue->setLatitude($latitude);
        $venue->setLongitude($longitude);

        $repository = $this->createStub(LocationRepository::class);
        $repository->method('findAll')->willReturn([$venue]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly($expectedWarnings))->method('warning');

        $subject = new SwapReversedVenueCoordinates($repository, $this->createStub(EntityManagerInterface::class), $logger);

        // Act
        $subject->execute();

        // Assert
        static::assertSame($expectedLatitude, $venue->getLatitude());
        static::assertSame($expectedLongitude, $venue->getLongitude());
    }

    public function testIdentifierIsDatePrefixed(): void
    {
        // Arrange
        $subject = new SwapReversedVenueCoordinates(
            $this->createStub(LocationRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        // Act
        $id = $subject->getIdentifier();

        // Assert
        static::assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_/', $id);
    }
}
