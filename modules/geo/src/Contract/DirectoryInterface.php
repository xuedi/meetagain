<?php declare(strict_types=1);

namespace Module\Geo\Contract;

/**
 * The whole outbound surface of the Geo module. Every name is resolved for the requested locale,
 * falling back to English and then to the ASCII name, so a caller never sees a missing name.
 */
interface DirectoryInterface
{
    public const int SEARCH_LIMIT = 10;

    public function hasCities(): bool;

    public function findCity(int $cityId, string $locale): ?CityView;

    /**
     * Ids the directory does not know are left out.
     *
     * @param list<int> $cityIds
     * @return array<int, CityView> keyed by city id
     */
    public function findCities(array $cityIds, string $locale): array;

    public function findCityBySlug(string $countryIso2, string $slug, string $locale): ?CityView;

    public function findCityByGeonameId(int $geonameId, string $locale): ?CityView;

    /**
     * Matches the start of any stored name of a city, most populous first, at most SEARCH_LIMIT.
     *
     * @return list<CityView>
     */
    public function searchCities(string $query, ?string $countryIso2, string $locale): array;

    /**
     * Every city any of whose names is the given name, letter case and accents aside, most populous first.
     *
     * @return list<CityView>
     */
    public function findCitiesNamed(string $name, string $locale): array;

    /**
     * @return list<CountryView> ordered by their name in the locale
     */
    public function listCountries(string $locale): array;

    /**
     * @param list<int> $cityIds
     * @return array<int, string> country ISO2 keyed by city id
     */
    public function findCountryCodes(array $cityIds): array;

    /**
     * @return array<string, int> imported cities keyed by country ISO2, countries without a city left out
     */
    public function countCitiesPerCountry(): array;

    /**
     * The country, else the most populous city, that any of whose names slugs to the label - also when the label
     * spells an umlaut as ae, oe or ue.
     */
    public function findPlaceNamed(string $label, string $locale): CityView|CountryView|null;
}
