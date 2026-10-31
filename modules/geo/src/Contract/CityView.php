<?php declare(strict_types=1);

namespace Module\Geo\Contract;

final readonly class CityView
{
    public function __construct(
        public int $id,
        public int $geonameId,
        public string $slug,
        public string $countryIso2,
        public string $name,
        public string $countryName,
        public float $latitude,
        public float $longitude,
        public string $timezone,
        public int $population,
    ) {}
}
