<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

final readonly class Options
{
    public const string DEFAULT_BASE_URL = 'https://download.geonames.org/export/dump/';
    public const int DEFAULT_MIN_POPULATION = 15000;
    public const array CITY_DUMPS = [500, 1000, 5000, 15000];

    public ?string $country;

    public function __construct(
        ?string $country = null,
        public ?string $sourceDir = null,
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public int $minPopulation = self::DEFAULT_MIN_POPULATION,
    ) {
        $this->country = $country === null || $country === '' ? null : strtoupper($country);
    }

    public function citiesFile(): string
    {
        $fitting = array_filter(self::CITY_DUMPS, fn(int $size): bool => $size <= $this->minPopulation);

        return sprintf('cities%d.zip', $fitting === [] ? min(self::CITY_DUMPS) : max($fitting));
    }

    public function covers(string $iso2): bool
    {
        return $this->country === null || $this->country === $iso2;
    }
}
