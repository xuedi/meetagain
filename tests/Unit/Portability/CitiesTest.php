<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\User;
use App\Portability\Cities;
use App\Portability\ImageImporter;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\DirectoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CitiesTest extends TestCase
{
    private const int CITY_ID = 7;
    private const int BERLIN = 2950159;

    public function testACityTravelsByItsGeonamesId(): void
    {
        // Arrange
        $cities = new Cities($this->directory());

        // Act
        $exported = $cities->export(self::CITY_ID);

        // Assert
        self::assertSame(['city_geoname_id' => self::BERLIN, 'city_name' => 'Berlin', 'country' => 'DE'], $exported);
    }

    public function testNoCityExportsEmptyFields(): void
    {
        // Arrange
        $cities = new Cities($this->directory());

        // Act
        $exported = $cities->export(null);

        // Assert
        self::assertSame(['city_geoname_id' => null, 'city_name' => null, 'country' => ''], $exported);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int|null, array<string, int>}>
     */
    public static function provideImportedRows(): iterable
    {
        yield 'a known city' => [['city_geoname_id' => self::BERLIN], self::CITY_ID, [Outcome::Matched->value => 1]];
        yield 'an unknown city is dropped and counted' => [['city_geoname_id' => 1], null, [Outcome::Dropped->value => 1]];
        yield 'a row without a city' => [['city_geoname_id' => null], null, []];
        yield 'an older archive without the field' => [[], null, []];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $expectedCounts
     */
    #[DataProvider('provideImportedRows')]
    public function testAnImportResolvesTheCityInThisInstallation(array $row, ?int $expectedId, array $expectedCounts): void
    {
        // Arrange
        $cities = new Cities($this->directory());
        $context = new ImportContext(self::createStub(ImageImporter::class), sys_get_temp_dir(), new User());

        // Act
        $cityId = $cities->resolve($row, $context, Cities::LOCATION_KIND);

        // Assert
        self::assertSame($expectedId, $cityId);
        self::assertSame($expectedCounts, $context->toSummary()->counts[Cities::LOCATION_KIND] ?? []);
    }

    private function directory(): DirectoryInterface
    {
        $berlin = new CityView(self::CITY_ID, self::BERLIN, 'berlin', 'DE', 'Berlin', 'Germany', 52.52, 13.41, 'Europe/Berlin', 3426354);
        $directory = self::createStub(DirectoryInterface::class);
        $directory->method('findCity')->willReturnCallback(static fn(int $id): ?CityView => $id === self::CITY_ID ? $berlin : null);
        $directory->method('findCityByGeonameId')->willReturnCallback(static fn(int $id): ?CityView => $id === self::BERLIN ? $berlin : null);

        return $directory;
    }
}
