<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use App\Service\Config\LanguageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Import\Dataset;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\Import\Options;
use Module\Geo\Internal\ImportAction;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Task\Contract\Status;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportActionTest extends KernelTestCase
{
    private const string SAMPLE = __DIR__ . '/../Sample';
    private const int SMALL_STEP = 4;
    private const array TABLES = [
        'mod_geo_city_translation',
        'mod_geo_district_translation',
        'mod_geo_state_translation',
        'mod_geo_country_translation',
        'mod_geo_district',
        'mod_geo_city',
        'mod_geo_state',
        'mod_geo_country',
    ];

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTheImportAsksForNothing(): void
    {
        // Act
        $parameters = $this->action()->getParameters();

        // Assert
        self::assertSame([], $parameters);
    }

    public function testTheFactsCountTheDirectoryAndListNoCountryCodes(): void
    {
        // Arrange
        $this->walk();

        // Act
        $facts = $this->action()->getFacts();

        // Assert
        self::assertSame(
            [
                'admin_geo_import.fact_countries' => '3',
                'admin_geo_import.fact_states' => '14',
                'admin_geo_import.fact_cities' => '15',
                'admin_geo_import.fact_districts' => '2',
                'admin_geo_import.fact_translations' => (string) $this->rowsOf('mod_geo_city_translation'),
            ],
            $facts,
        );
    }

    public function testTheStepsWalkFromCountriesThroughWindowsToOneCountryOfNamesAtATime(): void
    {
        // Act
        $cursors = $this->walk();

        // Assert
        self::assertSame(
            [null, 'cities:0', 'cities:4', 'cities:8', 'cities:12', 'districts:0', 'translations:CN', 'translations:DE', 'translations:US'],
            $cursors,
        );
    }

    public function testSmallStepsFillTheSameDirectoryAsOneRunPerDataset(): void
    {
        // Arrange
        foreach (Dataset::cases() as $dataset) {
            $this->loader()->import($dataset, $this->options());
        }
        $inOneGo = $this->snapshot();
        $this->wipe();

        // Act
        $this->walk();

        // Assert
        self::assertSame($inOneGo, $this->snapshot());
    }

    public function testASecondRunWalksTheSameStepsAndChangesNothing(): void
    {
        // Arrange
        $firstCursors = $this->walk();
        $first = $this->snapshot();

        // Act
        $cursors = $this->walk();

        // Assert
        self::assertSame($firstCursors, $cursors);
        self::assertSame($first, $this->snapshot());
    }

    public function testNamesFollowTheEnabledLanguages(): void
    {
        // Act
        $this->walk();

        // Assert
        self::assertSame(['北京', 'Beijing'], [$this->cityName(1816670, 'zh'), $this->cityName(1816670, 'fr')]);
    }

    public function testANewlyEnabledLanguageFetchesNamesForEveryCountryAgain(): void
    {
        // Arrange
        $this->walk();
        $this->enable('fr');

        // Act
        $cursors = $this->walk();

        // Assert
        self::assertSame(['translations:CN', 'translations:DE', 'translations:US'], array_slice($cursors, -3));
        self::assertSame('Pékin', $this->cityName(1816670, 'fr'));
    }

    public function testAnUnknownStepIsAnErrorNotARun(): void
    {
        // Act
        $result = $this->action()->step($this->options(), 'planets:0');

        // Assert
        self::assertSame(Status::error, $result->status);
        self::assertNull($result->continueFrom);
    }

    public function testWithNoArgumentsTheOptionsAreGeoNamesAndTheEnabledLanguages(): void
    {
        // Act
        $options = $this->action()->options();

        // Assert
        self::assertNull($options->sourceDir);
        self::assertSame(Options::DEFAULT_BASE_URL, $options->baseUrl);
        self::assertSame(Options::DEFAULT_MIN_POPULATION, $options->minPopulation);
        self::assertSame($this->languages()->getEnabledCodes(), $options->languages);
    }

    /**
     * @return list<string|null> the cursor each step started from
     */
    private function walk(): array
    {
        $action = $this->smallStepAction();
        $options = $this->options();
        $cursors = [];
        $cursor = null;
        do {
            $cursors[] = $cursor;
            $result = $action->step($options, $cursor);
            self::assertSame(Status::ok, $result->status, $result->message);
            $cursor = $result->continueFrom;
        } while ($cursor !== null);

        return $cursors;
    }

    private function options(): Options
    {
        return $this->action()->options(sourceDir: self::SAMPLE);
    }

    private function smallStepAction(): ImportAction
    {
        $cities = self::getContainer()->get(CityRepository::class);
        self::assertInstanceOf(CityRepository::class, $cities);

        return new ImportAction($this->loader(), $cities, $this->entityManager(), $this->languages(), self::SMALL_STEP);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        return [
            'cities' => $this->connection()->fetchAllAssociative(
                'SELECT geoname_id, slug, name_slug, state_id IS NULL AS stateless FROM mod_geo_city ORDER BY geoname_id',
            ),
            'districts' => $this->connection()->fetchAllAssociative('SELECT geoname_id, slug FROM mod_geo_district ORDER BY geoname_id'),
            'countries' => $this->connection()->fetchAllAssociative(
                'SELECT iso2, latitude, longitude, bbox_north, bbox_south, capital_geoname_id FROM mod_geo_country ORDER BY iso2',
            ),
            'names' => $this->connection()->fetchAllAssociative(
                'SELECT c.geoname_id, t.language, t.name FROM mod_geo_city_translation t JOIN mod_geo_city c ON c.id = t.city_id ORDER BY c.geoname_id, t.language',
            ),
        ];
    }

    private function wipe(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection()->executeStatement('DELETE FROM ' . $table);
        }
    }

    private function enable(string $code): void
    {
        $this->languages()->findByCode($code)?->setEnabled(true);
        $this->entityManager()->flush();
        $this->languages()->invalidateCache();
    }

    private function cityName(int $geonameId, string $locale): ?string
    {
        return $this->directory()->findCityByGeonameId($geonameId, $locale)?->name;
    }

    private function rowsOf(string $table): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function action(): ImportAction
    {
        $action = self::getContainer()->get(ImportAction::class);
        self::assertInstanceOf(ImportAction::class, $action);

        return $action;
    }

    private function loader(): Loader
    {
        $loader = self::getContainer()->get(Loader::class);
        self::assertInstanceOf(Loader::class, $loader);

        return $loader;
    }

    private function languages(): LanguageService
    {
        $languages = self::getContainer()->get(LanguageService::class);
        self::assertInstanceOf(LanguageService::class, $languages);

        return $languages;
    }

    private function directory(): DirectoryInterface
    {
        $directory = self::getContainer()->get('geo.test.directory');
        self::assertInstanceOf(DirectoryInterface::class, $directory);

        return $directory;
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
