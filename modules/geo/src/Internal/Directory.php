<?php declare(strict_types=1);

namespace Module\Geo\Internal;

use Collator;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\CountryView;
use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Geo\Internal\Repository\CountryRepository;
use Override;
use Symfony\Contracts\Service\ResetInterface;

class Directory implements DirectoryInterface, ResetInterface
{
    private const string FALLBACK_LANGUAGE = 'en';

    private ?bool $hasCities = null;

    public function __construct(
        private readonly CityRepository $cities,
        private readonly CountryRepository $countries,
        private readonly Slugs $slugs,
    ) {}

    #[Override]
    public function hasCities(): bool
    {
        return $this->hasCities ??= $this->cities->hasAny();
    }

    #[Override]
    public function findCity(int $cityId, string $locale): ?CityView
    {
        return $this->findCities([$cityId], $locale)[$cityId] ?? null;
    }

    #[Override]
    public function findCities(array $cityIds, string $locale): array
    {
        $views = [];
        foreach ($this->toViews($this->cities->findWithCountry($cityIds), $locale) as $view) {
            $views[$view->id] = $view;
        }

        return $views;
    }

    #[Override]
    public function findCityBySlug(string $countryIso2, string $slug, string $locale): ?CityView
    {
        return $this->toView($this->cities->findOneBySlug($countryIso2, $slug), $locale);
    }

    #[Override]
    public function findCityByGeonameId(int $geonameId, string $locale): ?CityView
    {
        return $this->toView($this->cities->findOneByGeonameId($geonameId), $locale);
    }

    #[Override]
    public function searchCities(string $query, ?string $countryIso2, string $locale): array
    {
        $name = trim($query);
        if ($name === '') {
            return [];
        }

        $ids = $this->cities->searchIds($name, $this->slugs->of($name), $countryIso2, self::SEARCH_LIMIT);
        $views = $this->findCities($ids, $locale);

        return array_values(array_filter(array_map(static fn(int $id): ?CityView => $views[$id] ?? null, $ids)));
    }

    #[Override]
    public function findCitiesNamed(string $name, string $locale): array
    {
        $nameSlug = $this->slugs->of($name);
        if ($nameSlug === '') {
            return [];
        }

        $ids = $this->cities->findIdsByNameSlug($nameSlug);
        $views = $this->findCities($ids, $locale);

        return array_values(array_filter(array_map(static fn(int $id): ?CityView => $views[$id] ?? null, $ids)));
    }

    #[Override]
    public function listCountries(string $locale): array
    {
        $views = $this->toCountryViews($this->countries->findAll(), $locale);
        $collator = new Collator($locale);
        usort($views, static fn(CountryView $a, CountryView $b): int => (int) $collator->compare($a->name, $b->name));

        return $views;
    }

    #[Override]
    public function findCountryCodes(array $cityIds): array
    {
        return $this->cities->findCountryCodes($cityIds);
    }

    #[Override]
    public function countCitiesPerCountry(): array
    {
        return $this->cities->countPerCountry();
    }

    #[Override]
    public function findPlaceNamed(string $label, string $locale): CityView|CountryView|null
    {
        $nameSlug = strtolower(trim($label));
        if ($nameSlug === '') {
            return null;
        }

        foreach (array_unique([$nameSlug, str_replace(['ae', 'oe', 'ue'], ['a', 'o', 'u'], $nameSlug)]) as $candidate) {
            $place = $this->findPlaceBySlug($candidate, $locale);
            if ($place !== null) {
                return $place;
            }
        }

        return null;
    }

    #[Override]
    public function reset(): void
    {
        $this->hasCities = null;
    }

    private function findPlaceBySlug(string $nameSlug, string $locale): CityView|CountryView|null
    {
        $country = $this->countries->findOneByNameSlug($nameSlug);
        if ($country !== null) {
            return $this->toCountryViews([$country], $locale)[0];
        }

        return $this->toView($this->cities->findMostPopulousByNameSlug($nameSlug), $locale);
    }

    private function toView(?City $city, string $locale): ?CityView
    {
        return $city === null ? null : $this->toViews([$city], $locale)[0];
    }

    /**
     * @param list<City> $cities
     * @return list<CityView>
     */
    private function toViews(array $cities, string $locale): array
    {
        $languages = $this->languages($locale);
        $cityIds = array_map(static fn(City $city): int => (int) $city->getId(), $cities);
        $cityNames = $this->cities->findTranslatedNames($cityIds, $languages);
        $countries = [];
        foreach ($cities as $city) {
            $countries[(int) $city->getCountry()->getId()] = $city->getCountry();
        }
        $countryNames = $this->countryNames(array_values($countries), $languages);

        return array_map(
            fn(City $city): CityView => new CityView(
                id: (int) $city->getId(),
                geonameId: $city->getGeonameId(),
                slug: $city->getSlug(),
                countryIso2: $city->getCountry()->getIso2(),
                name: $this->pick($cityNames[(int) $city->getId()] ?? [], $languages, $city->getAsciiName()),
                countryName: $countryNames[(int) $city->getCountry()->getId()],
                latitude: (float) $city->getLatitude(),
                longitude: (float) $city->getLongitude(),
                timezone: $city->getTimezone(),
                population: $city->getPopulation(),
            ),
            $cities,
        );
    }

    /**
     * @param list<Country> $countries
     * @return list<CountryView>
     */
    private function toCountryViews(array $countries, string $locale): array
    {
        $names = $this->countryNames($countries, $this->languages($locale));

        return array_map(
            static fn(Country $country): CountryView => new CountryView(
                id: (int) $country->getId(),
                geonameId: $country->getGeonameId(),
                iso2: $country->getIso2(),
                name: $names[(int) $country->getId()],
                population: $country->getPopulation(),
                latitude: $country->getLatitude() === null ? null : (float) $country->getLatitude(),
                longitude: $country->getLongitude() === null ? null : (float) $country->getLongitude(),
            ),
            $countries,
        );
    }

    /**
     * @param list<Country> $countries
     * @param list<string> $languages
     * @return array<int, string>
     */
    private function countryNames(array $countries, array $languages): array
    {
        $ids = array_map(static fn(Country $country): int => (int) $country->getId(), $countries);
        $translated = $this->countries->findTranslatedNames($ids, $languages);
        $names = [];
        foreach ($countries as $country) {
            $id = (int) $country->getId();
            $names[$id] = $this->pick($translated[$id] ?? [], $languages, $country->getName());
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function languages(string $locale): array
    {
        return array_values(array_unique([$locale, self::FALLBACK_LANGUAGE]));
    }

    /**
     * @param array<string, string> $names
     * @param list<string> $languages
     */
    private function pick(array $names, array $languages, string $fallback): string
    {
        $language = array_find($languages, static fn(string $language): bool => isset($names[$language]));

        return $language === null ? $fallback : $names[$language];
    }
}
