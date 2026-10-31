<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Doctrine\DBAL\Connection;
use Module\Geo\Contract\DirectoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportTest extends KernelTestCase
{
    private const string SAMPLE = __DIR__ . '/../Sample';
    private const array TABLES = [
        'mod_geo_country',
        'mod_geo_state',
        'mod_geo_city',
        'mod_geo_district',
        'mod_geo_city_translation',
        'mod_geo_country_translation',
    ];

    private CommandTester $command;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->command = new CommandTester(new Application(self::$kernel)->find('app:geo:import'));
    }

    public function testTheSampleFillsEveryEntity(): void
    {
        // Act
        $status = $this->import('all');

        // Assert
        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([3, 14, 15, 2], array_slice($this->rowCounts(), 0, 4));
    }

    public function testASecondRunAddsNothing(): void
    {
        // Arrange
        $this->import('all');
        $first = $this->rowCounts();

        // Act
        $this->import('all');

        // Assert
        self::assertSame($first, $this->rowCounts());
    }

    public function testACityBelowTheMinimumPopulationIsLeftOut(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertNull($this->directory()->findCityByGeonameId(5083330, 'en'));
    }

    public function testTheMorePopulousOfTwoSameNamedCitiesKeepsThePlainSlug(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame(['springfield-2', 'springfield'], [
            $this->directory()->findCityByGeonameId(4250542, 'en')?->slug,
            $this->directory()->findCityByGeonameId(4409896, 'en')?->slug,
        ]);
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function provideImportedNames(): iterable
    {
        yield 'a preferred name over a plain alternate' => [2867714, 'de', 'München'];
        yield 'a preferred name over an earlier plain one' => [2925533, 'en', 'Frankfurt am Main'];
        yield 'a historic name is left out' => [1816670, 'en', 'Beijing'];
        yield 'a historic name never wins a language' => [1809858, 'de', 'Guangzhou'];
        yield 'a language outside the locales is left out' => [2950159, 'fr', 'Berlin'];
    }

    #[DataProvider('provideImportedNames')]
    public function testTheImportPicksTheRightName(int $geonameId, string $locale, string $expected): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame($expected, $this->directory()->findCityByGeonameId($geonameId, $locale)?->name);
    }

    public function testAPreferredShortCountryNameBeatsThePreferredLongOne(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame('Deutschland', $this->directory()->findCityByGeonameId(2950159, 'de')?->countryName);
    }

    public function testAShortCountryNameLosesToThePreferredOne(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame('United States', $this->directory()->findCityByGeonameId(5128581, 'en')?->countryName);
    }

    public function testADistrictBelongsToTheNearestLargerCity(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame(
            [['slug' => 'kreuzberg', 'city' => 'Berlin'], ['slug' => 'prenzlauer-berg', 'city' => 'Berlin']],
            $this->connection()->fetchAllAssociative(
                'SELECT d.slug, c.ascii_name AS city FROM mod_geo_district d JOIN mod_geo_city c ON c.id = d.city_id ORDER BY d.slug',
            ),
        );
    }

    public function testADistrictIsNotACity(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertNull($this->directory()->findCityByGeonameId(2884936, 'en'));
    }

    public function testACountryIsPlacedAtItsCapitalAndBoundedByItsCities(): void
    {
        // Act
        $this->import('all');

        // Assert
        self::assertSame(
            [
                'latitude' => '52.5243700',
                'longitude' => '13.4105300',
                'bbox_north' => '53.5507300',
                'bbox_west' => '6.9500000',
                'capital_geoname_id' => 2950159,
            ],
            $this->connection()->fetchAssociative(
                "SELECT latitude, longitude, bbox_north, bbox_west, capital_geoname_id FROM mod_geo_country WHERE iso2 = 'DE'",
            ),
        );
    }

    public function testACountryLimitsEveryDataset(): void
    {
        // Act
        $this->import('all', ['--country' => 'de']);

        // Assert
        self::assertSame(
            ['DE'],
            $this->connection()->fetchFirstColumn('SELECT DISTINCT co.iso2 FROM mod_geo_city c JOIN mod_geo_country co ON co.id = c.country_id'),
        );
        self::assertSame(1, $this->rowCounts()[0]);
    }

    public function testAnUnknownDatasetIsRefused(): void
    {
        // Act
        $status = $this->import('planets');

        // Assert
        self::assertSame(Command::INVALID, $status);
    }

    /**
     * @param array<string, string> $options
     */
    private function import(string $dataset, array $options = []): int
    {
        return $this->command->execute(['dataset' => $dataset, '--source' => self::SAMPLE, ...$options]);
    }

    /**
     * @return list<int>
     */
    private function rowCounts(): array
    {
        return array_map(fn(string $table): int => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ' . $table), self::TABLES);
    }

    private function directory(): DirectoryInterface
    {
        return self::getContainer()->get(DirectoryInterface::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
