<?php declare(strict_types=1);

namespace Module\Geo\Internal;

use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\CityTranslation;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Entity\District;
use Module\Geo\Internal\Entity\State;
use Module\Geo\Internal\Import\Dataset;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\Import\Options;
use Module\Geo\Internal\Import\Report;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\ActionSettingsInterface;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ImportAction implements ActionInterface
{
    public const string IDENTIFIER = 'geo-import';
    public const string ALL = 'all';
    public const string DATASET = 'dataset';
    public const string COUNTRY = 'country';
    public const string BASE_URL = 'base_url';
    public const string MIN_POPULATION = 'min_population';

    public function __construct(
        private Loader $loader,
        private DirectoryInterface $directory,
        private CityRepository $cities,
        private EntityManagerInterface $entityManager,
        private ActionSettingsInterface $settings,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::ReferenceData;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_geo_import.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_geo_import.description';
    }

    #[Override]
    public function getParameters(): array
    {
        $datasets = ['admin_geo_import.dataset_all' => self::ALL];
        foreach (Dataset::cases() as $dataset) {
            $datasets['admin_geo_import.dataset_' . $dataset->value] = $dataset->value;
        }
        $countries = ['admin_geo_import.country_all' => ''];
        foreach ($this->directory->listCountries($this->translator->getLocale()) as $country) {
            $countries[$country->name] = $country->iso2;
        }

        return [
            ActionParameter::choice(self::DATASET, 'admin_geo_import.param_dataset', $datasets, self::ALL),
            ActionParameter::choice(self::COUNTRY, 'admin_geo_import.param_country', $countries, ''),
            ActionParameter::text(self::BASE_URL, 'admin_geo_import.param_base_url', Options::DEFAULT_BASE_URL, remembered: true),
            ActionParameter::number(self::MIN_POPULATION, 'admin_geo_import.param_min_population', Options::DEFAULT_MIN_POPULATION, remembered: true),
        ];
    }

    #[Override]
    public function getFacts(): array
    {
        return [
            'admin_geo_import.fact_countries' => (string) $this->entityManager->getRepository(Country::class)->count(),
            'admin_geo_import.fact_states' => (string) $this->entityManager->getRepository(State::class)->count(),
            'admin_geo_import.fact_cities' => (string) $this->entityManager->getRepository(City::class)->count(),
            'admin_geo_import.fact_districts' => (string) $this->entityManager->getRepository(District::class)->count(),
            'admin_geo_import.fact_translations' => (string) $this->entityManager->getRepository(CityTranslation::class)->count(),
            'admin_geo_import.fact_countries_with_cities' => $this->codes($this->cities->findCountriesWithCities()),
            'admin_geo_import.fact_countries_with_translations' => $this->codes($this->cities->findCountriesWithTranslations()),
        ];
    }

    #[Override]
    public function run(array $values): Result
    {
        $name = is_string($values[self::DATASET] ?? null) ? $values[self::DATASET] : self::ALL;
        $datasets = $name === self::ALL ? Dataset::cases() : array_values(array_filter([Dataset::tryFrom($name)]));
        if ($datasets === []) {
            return new Result(Status::error, sprintf('Unknown dataset "%s".', $name));
        }

        $options = $this->options(
            country: is_string($values[self::COUNTRY] ?? null) ? $values[self::COUNTRY] : null,
            baseUrl: is_string($values[self::BASE_URL] ?? null) ? $values[self::BASE_URL] : null,
            minPopulation: is_int($values[self::MIN_POPULATION] ?? null) ? $values[self::MIN_POPULATION] : null,
        );
        try {
            $reports = array_map(fn(Dataset $dataset): Report => $this->loader->import($dataset, $options), $datasets);
        } catch (RuntimeException $exception) {
            return new Result(Status::error, $exception->getMessage());
        }

        return new Result(Status::ok, implode('; ', array_map(static fn(Report $report): string => sprintf(
            '%s: %d created, %d updated, %d unchanged, %d skipped',
            $report->dataset->value,
            $report->created,
            $report->updated,
            $report->unchanged,
            $report->skipped,
        ), $reports)));
    }

    public function options(?string $country = null, ?string $sourceDir = null, ?string $baseUrl = null, ?int $minPopulation = null): Options
    {
        $baseUrl ??= $this->remembered(self::BASE_URL) ?? Options::DEFAULT_BASE_URL;
        if ($sourceDir === null && preg_match('#^https?://#i', $baseUrl) !== 1) {
            $sourceDir = $baseUrl;
        }
        $saved = $this->settings->get(self::IDENTIFIER, self::MIN_POPULATION);

        return new Options(
            country: $country,
            sourceDir: $sourceDir,
            baseUrl: $baseUrl,
            minPopulation: $minPopulation ?? (is_int($saved) ? $saved : Options::DEFAULT_MIN_POPULATION),
        );
    }

    private function remembered(string $key): ?string
    {
        $value = $this->settings->get(self::IDENTIFIER, $key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param list<string> $codes
     */
    private function codes(array $codes): string
    {
        return $codes === [] ? '-' : implode(', ', $codes);
    }
}
