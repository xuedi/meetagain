<?php declare(strict_types=1);

namespace Module\Geo\Contract;

final readonly class CountryView
{
    public function __construct(
        public int $id,
        public int $geonameId,
        public string $iso2,
        public string $name,
        public int $population,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
