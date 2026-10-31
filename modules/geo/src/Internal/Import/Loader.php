<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Entity\District;
use Module\Geo\Internal\Entity\State;
use Module\Geo\Internal\Entity\Translation;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Geo\Internal\Repository\CountryRepository;
use Module\Geo\Internal\Repository\DistrictRepository;
use Module\Geo\Internal\Repository\StateRepository;
use Module\Geo\Internal\Repository\TranslationRepository;
use Module\Geo\Internal\Slugs;

readonly class Loader
{
    public const array LANGUAGES = ['en', 'de', 'zh', 'fr', 'es'];
    public const int DISTRICT_RADIUS_KM = 25;

    private const int BATCH_SIZE = 500;
    private const array CITY_SEATS = ['PPLC', 'PPLA'];
    private const array NOT_A_CITY = ['PPLX', 'PPLH', 'PPLQ', 'PPLW', 'PPLCH'];
    private const string DISTRICT_CODE = 'PPLX';
    private const string POPULATED_PLACE = 'P';
    private const int EARTH_RADIUS_KM = 6371;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Files $files,
        private Slugs $slugs,
        private CountryRepository $countries,
        private StateRepository $states,
        private CityRepository $cities,
        private DistrictRepository $districts,
        private TranslationRepository $translations,
    ) {}

    /**
     * @param Closure(): void|null $tick
     */
    public function import(Dataset $dataset, Options $options, ?Closure $tick = null): Report
    {
        $tick ??= static function (): void {};

        return match ($dataset) {
            Dataset::Countries => $this->importCountries($options, $tick),
            Dataset::States => $this->importStates($options, $tick),
            Dataset::Cities => $this->importCities($options, $tick),
            Dataset::Districts => $this->importDistricts($options, $tick),
            Dataset::Translations => $this->importTranslations($options, $tick),
        };
    }

    /**
     * @param Closure(): void $tick
     */
    private function importCountries(Options $options, Closure $tick): Report
    {
        $existing = $this->countries->findAllByIso2();
        $tally = new Tally();
        foreach ($this->files->rows($options, 'countryInfo.txt') as $row) {
            $tick();
            $iso2 = $row[0];
            if (!$options->covers($iso2)) {
                continue;
            }
            $geonameId = (int) ($row[16] ?? 0);
            if ($geonameId === 0 || strlen($iso2) !== 2) {
                $tally->skipped();
                continue;
            }

            $name = $this->clip($row[4]);
            $country = $existing[$iso2] ?? null;
            if ($country === null) {
                $country = new Country($geonameId, $iso2, $row[1], $name, $this->slugs->of($name));
                $this->entityManager->persist($country);
                $tally->created();
            } else {
                $country->rename($row[1], $name, $this->slugs->of($name));
                $tally->updated();
            }
            $country
                ->setNumericCode($row[2] === '' ? null : (int) $row[2])
                ->setPopulation((int) $row[7])
                ->setContinent($row[8] === '' ? null : $row[8])
                ->setCurrencyCode($row[10] === '' ? null : $row[10])
                ->setPhoneCode($row[12] === '' ? null : substr($row[12], 0, 20));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $tally->report(Dataset::Countries);
    }

    /**
     * @param Closure(): void $tick
     */
    private function importStates(Options $options, Closure $tick): Report
    {
        $countries = $this->countries->findAllByIso2();
        $existing = $this->states->findAllByCode();
        $tally = new Tally();
        foreach ($this->files->rows($options, 'admin1CodesASCII.txt') as $row) {
            $tick();
            [$iso2, $code] = array_pad(explode('.', $row[0], 2), 2, '');
            if (!$options->covers($iso2)) {
                continue;
            }
            $country = $countries[$iso2] ?? null;
            $geonameId = (int) ($row[3] ?? 0);
            if ($country === null || $code === '' || $geonameId === 0) {
                $tally->skipped();
                continue;
            }

            $state = $existing[$row[0]] ?? null;
            if ($state === null) {
                $state = new State($country, $code, $geonameId, $this->clip($row[2]));
                $this->entityManager->persist($state);
                $tally->created();
            } else {
                $state->setAsciiName($this->clip($row[2]));
                $tally->updated();
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $tally->report(Dataset::States);
    }

    /**
     * @param Closure(): void $tick
     */
    private function importCities(Options $options, Closure $tick): Report
    {
        $countryIds = array_map(static fn(Country $country): int => (int) $country->getId(), $this->countries->findAllByIso2());
        $stateIds = array_map(static fn(State $state): int => (int) $state->getId(), $this->states->findAllByCode());
        $this->entityManager->clear();
        $winners = $this->mostPopulousPerName($options);

        $taken = [];
        $touched = [];
        $tally = new Tally();
        $batch = [];
        foreach ($this->files->rows($options, $options->citiesFile()) as $row) {
            $tick();
            if (!$this->isCityRow($row, $options)) {
                continue;
            }
            if (!isset($countryIds[$row[8]])) {
                $tally->skipped();
                continue;
            }
            $batch[] = $row;
            $touched[$countryIds[$row[8]]] = true;
            if (count($batch) >= self::BATCH_SIZE) {
                $taken = $this->writeCities($batch, $countryIds, $stateIds, $winners, $taken, $tally);
                $batch = [];
            }
        }
        $this->writeCities($batch, $countryIds, $stateIds, $winners, $taken, $tally);
        $this->placeCountries(array_keys($touched));

        return $tally->report(Dataset::Cities);
    }

    /**
     * @param list<list<string>> $batch
     * @param array<string, int> $countryIds
     * @param array<string, int> $stateIds
     * @param array<string, int> $winners
     * @param array<int, array<string, true>> $taken
     * @return array<int, array<string, true>>
     */
    private function writeCities(array $batch, array $countryIds, array $stateIds, array $winners, array $taken, Tally $tally): array
    {
        $existing = $this->cities->findByGeonameIds(array_values(array_map(static fn(array $row): int => (int) $row[0], $batch)));
        foreach ($batch as $row) {
            $countryId = $countryIds[$row[8]];
            $geonameId = (int) $row[0];
            $asciiName = $this->clip($row[2]);
            $nameSlug = $this->nameSlug($asciiName, $geonameId);
            $stateId = $stateIds[$row[8] . '.' . $row[10]] ?? null;
            $city = $existing[$geonameId] ?? null;
            if ($city === null) {
                $taken[$countryId] ??= $this->cities->findSlugsOfCountry($countryId);
                $isMostPopulous = ($winners[$row[8] . '|' . $nameSlug] ?? $geonameId) === $geonameId;
                $slug = $this->slugs->nextFree($nameSlug, $taken[$countryId] + ($isMostPopulous ? [] : [$nameSlug => true]));
                $taken[$countryId][$slug] = true;
                $city = new City(
                    $this->entityManager->getReference(Country::class, $countryId),
                    $geonameId,
                    $asciiName,
                    $slug,
                    $nameSlug,
                    $row[4],
                    $row[5],
                    $row[17],
                    (int) $row[14],
                    $row[7],
                );
                $this->entityManager->persist($city);
                $tally->created();
            } else {
                $city
                    ->rename($asciiName, $nameSlug)
                    ->locate($row[4], $row[5])
                    ->setTimezone($row[17])
                    ->setPopulation((int) $row[14])
                    ->setFeatureCode($row[7]);
                $tally->updated();
            }
            $city->setState($stateId === null ? null : $this->entityManager->getReference(State::class, $stateId));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $taken;
    }

    /**
     * @return array<string, int> GeoNames id of the most populous city, keyed by `ISO2|nameSlug`
     */
    private function mostPopulousPerName(Options $options): array
    {
        $population = [];
        $winners = [];
        foreach ($this->files->rows($options, $options->citiesFile()) as $row) {
            if (!$this->isCityRow($row, $options)) {
                continue;
            }
            $key = $row[8] . '|' . $this->nameSlug($this->clip($row[2]), (int) $row[0]);
            if ((int) $row[14] > ($population[$key] ?? -1)) {
                $population[$key] = (int) $row[14];
                $winners[$key] = (int) $row[0];
            }
        }

        return $winners;
    }

    /**
     * @param list<string> $row
     */
    private function isCityRow(array $row, Options $options): bool
    {
        if (count($row) < 18 || $row[6] !== self::POPULATED_PLACE || in_array($row[7], self::NOT_A_CITY, true)) {
            return false;
        }
        $largeEnough = (int) $row[14] >= $options->minPopulation || in_array($row[7], self::CITY_SEATS, true);

        return $largeEnough && $options->covers($row[8]) && trim($row[2]) !== '';
    }

    /**
     * @param list<int> $countryIds
     */
    private function placeCountries(array $countryIds): void
    {
        foreach ($countryIds as $countryId) {
            $country = $this->countries->find($countryId);
            $extent = $this->cities->findExtent($countryId);
            $capital = $this->cities->findCapitalOrLargest($countryId);
            if ($country === null || $extent === null || $capital === null) {
                continue;
            }
            $country
                ->bound($extent['north'], $extent['south'], $extent['east'], $extent['west'])
                ->locate($capital->getLatitude(), $capital->getLongitude())
                ->setCapitalGeonameId($capital->getFeatureCode() === 'PPLC' ? $capital->getGeonameId() : null);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param Closure(): void $tick
     */
    private function importDistricts(Options $options, Closure $tick): Report
    {
        $countryIds = array_map(static fn(Country $country): int => (int) $country->getId(), $this->countries->findAllByIso2());
        $stateIds = array_map(static fn(State $state): int => (int) $state->getId(), $this->states->findAllByCode());
        $taken = $this->districts->findSlugsByCity();
        $this->entityManager->clear();

        $points = [];
        $tally = new Tally();
        foreach ($this->files->rows($options, $options->citiesFile()) as $row) {
            $tick();
            if (count($row) < 18 || $row[7] !== self::DISTRICT_CODE || !$options->covers($row[8])) {
                continue;
            }
            $countryId = $countryIds[$row[8]] ?? null;
            if ($countryId === null) {
                $tally->skipped();
                continue;
            }
            $stateId = $stateIds[$row[8] . '.' . $row[10]] ?? null;
            $points[$countryId . '|' . $stateId] ??= $this->cities->findPointsOfState($countryId, $stateId);
            $cityId = $this->nearestLargerCity($points[$countryId . '|' . $stateId], (float) $row[4], (float) $row[5], (int) $row[14]);
            if ($cityId === null) {
                $tally->skipped();
                continue;
            }

            $geonameId = (int) $row[0];
            $asciiName = $this->clip($row[2]);
            $district = $this->districts->findByGeonameIds([$geonameId])[$geonameId] ?? null;
            if ($district === null) {
                $slug = $this->slugs->nextFree($this->nameSlug($asciiName, $geonameId), $taken[$cityId] ?? []);
                $taken[$cityId][$slug] = true;
                $district = new District($this->entityManager->getReference(City::class, $cityId), $geonameId, $asciiName, $slug);
                $this->entityManager->persist($district);
                $tally->created();
            } else {
                $district->setAsciiName($asciiName);
                $tally->updated();
            }
            $district->locate($row[4], $row[5])->setPopulation((int) $row[14]);
            if (($tally->written() % self::BATCH_SIZE) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $tally->report(Dataset::Districts);
    }

    /**
     * @param list<array{id: int, latitude: float, longitude: float, population: int}> $points
     */
    private function nearestLargerCity(array $points, float $latitude, float $longitude, int $population): ?int
    {
        $nearest = null;
        $nearestKm = self::DISTRICT_RADIUS_KM;
        foreach ($points as $point) {
            $km = $this->distanceKm($latitude, $longitude, $point['latitude'], $point['longitude']);
            if ($point['population'] > $population && $km <= $nearestKm) {
                $nearest = $point['id'];
                $nearestKm = $km;
            }
        }

        return $nearest;
    }

    private function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + (cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * (sin($longitudeDelta / 2) ** 2));

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($haversine)));
    }

    /**
     * @param Closure(): void $tick
     */
    private function importTranslations(Options $options, Closure $tick): Report
    {
        $countries = $this->countries->findAllByIso2();
        $iso2s = $options->country === null ? $this->cities->findCountriesWithCities() : [$options->country];
        $tally = new Tally();
        foreach ($iso2s as $iso2) {
            $country = $countries[$iso2] ?? null;
            if ($country === null) {
                $tally->skipped();
                continue;
            }
            $countryId = (int) $country->getId();
            $targets = [$country->getGeonameId() => ['country', $countryId]];
            foreach (['state' => $this->states, 'city' => $this->cities, 'district' => $this->districts] as $parent => $repository) {
                foreach ($repository->findIdsOfCountry($countryId) as $geonameId => $id) {
                    $targets[$geonameId] = [$parent, $id];
                }
            }

            $picks = new NamePicks();
            foreach ($this->files->rows($options, 'alternatenames/' . $iso2 . '.zip', optional: true) as $row) {
                $tick();
                $geonameId = (int) ($row[1] ?? 0);
                if (count($row) < 8 || !isset($targets[$geonameId]) || !in_array($row[2], self::LANGUAGES, true)) {
                    continue;
                }
                $picks->offer($geonameId, $row[2], $row[3], $row[4] === '1', $row[5] === '1', $row[6] === '1', $row[7] === '1');
            }

            $namesByParent = [];
            foreach ($picks->all() as $geonameId => $names) {
                [$parent, $id] = $targets[$geonameId];
                $namesByParent[$parent][$id] = $names;
            }
            foreach ($namesByParent as $parent => $namesById) {
                foreach (array_chunk($namesById, self::BATCH_SIZE, preserve_keys: true) as $chunk) {
                    $this->writeTranslations($parent, $chunk, $tally);
                }
            }
        }

        return $tally->report(Dataset::Translations);
    }

    /**
     * @param array<int, array<string, string>> $namesById
     */
    private function writeTranslations(string $parent, array $namesById, Tally $tally): void
    {
        $existing = $this->translations->findByParents($parent, array_keys($namesById));
        foreach ($namesById as $id => $names) {
            foreach ($names as $language => $name) {
                $name = $this->clip($name);
                $nameSlug = $this->slugs->of($name);
                $translation = $existing[$id][$language] ?? null;
                if ($translation === null) {
                    $this->translations->create($parent, $id, $language, $name, $nameSlug);
                    $tally->created();
                } elseif ($translation->getName() === $name && $translation->getNameSlug() === $nameSlug) {
                    $tally->unchanged();
                } else {
                    $translation->rename($name, $nameSlug);
                    $tally->updated();
                }
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function nameSlug(string $asciiName, int $geonameId): string
    {
        $slug = $this->slugs->of($asciiName);

        return $slug === '' ? (string) $geonameId : $slug;
    }

    private function clip(string $name): string
    {
        return mb_substr(trim($name), 0, Translation::MAX_NAME_LENGTH);
    }
}
