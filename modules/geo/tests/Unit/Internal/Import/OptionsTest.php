<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Internal\Import;

use Module\Geo\Internal\Import\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideMinimumPopulations(): iterable
    {
        yield 'the default' => [Options::DEFAULT_MIN_POPULATION, 'cities15000.zip'];
        yield 'above the largest dump' => [50000, 'cities15000.zip'];
        yield 'exactly a dump' => [5000, 'cities5000.zip'];
        yield 'between two dumps' => [1200, 'cities1000.zip'];
        yield 'below the smallest dump' => [100, 'cities500.zip'];
    }

    #[DataProvider('provideMinimumPopulations')]
    public function testTheMinimumPopulationPicksTheLargestDumpThatHoldsEveryCity(int $minPopulation, string $expected): void
    {
        // Arrange
        $options = new Options(minPopulation: $minPopulation);

        // Act
        $file = $options->citiesFile();

        // Assert
        self::assertSame($expected, $file);
    }

    /**
     * @return iterable<string, array{string|null, string, bool}>
     */
    public static function provideCountries(): iterable
    {
        yield 'every country' => [null, 'US', true];
        yield 'an empty country means every country' => ['', 'US', true];
        yield 'the chosen country' => ['de', 'DE', true];
        yield 'another country' => ['DE', 'US', false];
    }

    #[DataProvider('provideCountries')]
    public function testACountryLimitsTheImport(?string $country, string $iso2, bool $expected): void
    {
        // Arrange
        $options = new Options(country: $country);

        // Act
        $covered = $options->covers($iso2);

        // Assert
        self::assertSame($expected, $covered);
    }
}
