<?php declare(strict_types=1);

namespace Module\Geo\Internal;

use App\Service\Config\LanguageService;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
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
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;
use RuntimeException;

readonly class ImportAction implements ActionInterface
{
    public const string IDENTIFIER = 'geo-import';
    public const int ROWS_PER_STEP = 5000;

    /** @var array<string, class-string> */
    private const array COUNTED = [
        'countries' => Country::class,
        'states' => State::class,
        'cities' => City::class,
        'districts' => District::class,
        'translations' => CityTranslation::class,
    ];

    public function __construct(
        private Loader $loader,
        private CityRepository $cities,
        private EntityManagerInterface $entityManager,
        private LanguageService $languages,
        private int $rowsPerStep = self::ROWS_PER_STEP,
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
        return [];
    }

    #[Override]
    public function getFacts(): array
    {
        $facts = [];
        foreach ($this->counts() as $name => $count) {
            $facts['admin_geo_import.fact_' . $name] = (string) $count;
        }

        return $facts;
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        return $this->step($this->options(), $cursor);
    }

    /**
     * @param Closure(): void|null $tick
     */
    public function step(Options $options, ?string $cursor, ?Closure $tick = null): Result
    {
        [$stage, $at] = array_pad(explode(':', $cursor ?? '', 2), 2, '');
        try {
            return match ($stage) {
                '' => $this->countriesAndStates($options, $tick),
                Dataset::Cities->value => $this->window(Dataset::Cities, $options, (int) $at, $tick),
                Dataset::Districts->value => $this->window(Dataset::Districts, $options, (int) $at, $tick),
                Dataset::Translations->value => $this->translations($options, $at, $tick),
                default => new Result(Status::error, sprintf('Unknown step "%s".', $cursor)),
            };
        } catch (RuntimeException $exception) {
            return new Result(Status::error, $exception->getMessage());
        }
    }

    public function options(?string $country = null, ?string $sourceDir = null, ?string $baseUrl = null, ?int $minPopulation = null): Options
    {
        return new Options(
            country: $country,
            sourceDir: $sourceDir,
            baseUrl: $baseUrl ?? Options::DEFAULT_BASE_URL,
            minPopulation: $minPopulation ?? Options::DEFAULT_MIN_POPULATION,
            languages: $this->languages->getEnabledCodes(),
        );
    }

    /**
     * @param Closure(): void|null $tick
     */
    private function countriesAndStates(Options $options, ?Closure $tick): Result
    {
        $countries = $this->loader->import(Dataset::Countries, $options, $tick);
        $states = $this->loader->import(Dataset::States, $options, $tick);

        return new Result(Status::ok, $this->summary($countries) . '; ' . $this->summary($states), continueFrom: Dataset::Cities->value . ':0');
    }

    /**
     * @param Closure(): void|null $tick
     */
    private function window(Dataset $dataset, Options $options, int $fromRow, ?Closure $tick): Result
    {
        $report = $this->loader->import($dataset, $options->windowed($fromRow, $this->rowsPerStep), $tick);
        $nextRow = $fromRow + $this->rowsPerStep;
        $progress = sprintf('%s, rows %d to %d of %d', $this->summary($report), $fromRow + 1, min($nextRow, $report->rows), $report->rows);
        if ($nextRow < $report->rows) {
            return new Result(Status::ok, $progress, continueFrom: $dataset->value . ':' . $nextRow);
        }
        if ($dataset === Dataset::Cities) {
            return new Result(Status::ok, $progress, continueFrom: Dataset::Districts->value . ':0');
        }

        return $this->nextTranslation($options, null, $progress);
    }

    /**
     * @param Closure(): void|null $tick
     */
    private function translations(Options $options, string $iso2, ?Closure $tick): Result
    {
        $report = $this->loader->import(
            Dataset::Translations,
            new Options(
                country: $iso2,
                sourceDir: $options->sourceDir,
                baseUrl: $options->baseUrl,
                minPopulation: $options->minPopulation,
                languages: $options->languages,
            ),
            $tick,
        );

        return $this->nextTranslation($options, $iso2, $iso2 . ' ' . $this->summary($report));
    }

    private function nextTranslation(Options $options, ?string $after, string $progress): Result
    {
        $remaining = array_values(array_filter(
            $this->cities->findCountriesWithCities(),
            static fn(string $iso2): bool => $options->covers($iso2) && ($after === null || strcmp($iso2, $after) > 0),
        ));
        if ($remaining !== []) {
            return new Result(
                Status::ok,
                sprintf('%s; %d countries left to translate', $progress, count($remaining)),
                continueFrom: Dataset::Translations->value . ':' . $remaining[0],
            );
        }

        $counts = $this->counts();

        return new Result(Status::ok, sprintf(
            '%s; finished with %s',
            $progress,
            implode(', ', array_map(static fn(string $name, int $count): string => $count . ' ' . $name, array_keys($counts), $counts)),
        ));
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];
        foreach (self::COUNTED as $name => $class) {
            $counts[$name] = $this->entityManager->getRepository($class)->count();
        }

        return $counts;
    }

    private function summary(Report $report): string
    {
        return sprintf(
            '%s: %d created, %d updated, %d unchanged, %d skipped',
            $report->dataset->value,
            $report->created,
            $report->updated,
            $report->unchanged,
            $report->skipped,
        );
    }
}
