<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Import\Options;
use Module\Geo\Internal\ImportAction;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\Status;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportActionTest extends KernelTestCase
{
    private const string SAMPLE = __DIR__ . '/../Sample';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTheBaseAddressAndTheSmallestCityAreRemembered(): void
    {
        // Act
        $parameters = $this->action()->getParameters();

        // Assert
        $remembered = array_map(static fn(ActionParameter $parameter): bool => $parameter->remembered, array_column($parameters, null, 'key'));
        self::assertSame(
            [ImportAction::DATASET => false, ImportAction::COUNTRY => false, ImportAction::BASE_URL => true, ImportAction::MIN_POPULATION => true],
            $remembered,
        );
    }

    public function testTheCountryChoiceOffersOnlyEveryCountryBeforeAnImport(): void
    {
        // Act
        $countries = $this->parameter(ImportAction::COUNTRY)->choices;

        // Assert
        self::assertSame(['admin_geo_import.country_all' => ''], $countries);
    }

    public function testARunAgainstTheSampleFillsTheDirectory(): void
    {
        // Act
        $result = $this->action()->run([
            ImportAction::DATASET => ImportAction::ALL,
            ImportAction::COUNTRY => '',
            ImportAction::BASE_URL => self::SAMPLE,
            ImportAction::MIN_POPULATION => null,
        ]);

        // Assert
        self::assertSame(Status::ok, $result->status, $result->message);
        self::assertTrue($this->directory()->hasCities());
        self::assertContains('DE', array_values($this->parameter(ImportAction::COUNTRY)->choices));
    }

    public function testTheFactsCountWhatTheSampleImported(): void
    {
        // Arrange
        $this->action()->run([ImportAction::DATASET => ImportAction::ALL, ImportAction::BASE_URL => self::SAMPLE]);

        // Act
        $facts = $this->action()->getFacts();

        // Assert
        self::assertSame('3', $facts['admin_geo_import.fact_countries']);
        self::assertSame('15', $facts['admin_geo_import.fact_cities']);
        self::assertSame('CN, DE, US', $facts['admin_geo_import.fact_countries_with_cities']);
        self::assertSame('CN, DE, US', $facts['admin_geo_import.fact_countries_with_translations']);
    }

    public function testAnUnknownDatasetIsAnErrorNotARun(): void
    {
        // Act
        $result = $this->action()->run([ImportAction::DATASET => 'planets']);

        // Assert
        self::assertSame(Status::error, $result->status);
        self::assertFalse($this->directory()->hasCities());
    }

    public function testWithNothingSavedTheOptionsFallBackToGeoNames(): void
    {
        // Act
        $options = $this->action()->options();

        // Assert
        self::assertSame([Options::DEFAULT_BASE_URL, null, Options::DEFAULT_MIN_POPULATION], [$options->baseUrl, $options->sourceDir, $options->minPopulation]);
    }

    public function testALocalPathAsTheAddressIsReadAsADirectory(): void
    {
        // Act
        $options = $this->action()->options(baseUrl: self::SAMPLE);

        // Assert
        self::assertSame(self::SAMPLE, $options->sourceDir);
    }

    private function parameter(string $key): ActionParameter
    {
        $parameter = array_column($this->action()->getParameters(), null, 'key')[$key] ?? null;
        self::assertInstanceOf(ActionParameter::class, $parameter);

        return $parameter;
    }

    private function action(): ImportAction
    {
        $action = self::getContainer()->get(ImportAction::class);
        self::assertInstanceOf(ImportAction::class, $action);

        return $action;
    }

    private function directory(): DirectoryInterface
    {
        $directory = self::getContainer()->get('geo.test.directory');
        self::assertInstanceOf(DirectoryInterface::class, $directory);

        return $directory;
    }
}
