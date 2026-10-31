<?php declare(strict_types=1);

namespace App\Portability;

use Module\Geo\Contract\DirectoryInterface;

readonly class Cities
{
    public const string LOCATION_KIND = 'location_cities';
    public const string USER_KIND = 'user_cities';

    private const string READER_LOCALE = 'en';

    public function __construct(
        private DirectoryInterface $directory,
    ) {}

    /**
     * @return array{city_geoname_id: int|null, city_name: string|null, country: string}
     */
    public function export(?int $cityId): array
    {
        $city = $cityId === null ? null : $this->directory->findCity($cityId, self::READER_LOCALE);

        return [
            'city_geoname_id' => $city?->geonameId,
            'city_name' => $city?->name,
            'country' => $city === null ? '' : $city->countryIso2,
        ];
    }

    public function geonameIdOf(?int $cityId): ?int
    {
        return $cityId === null ? null : $this->directory->findCity($cityId, self::READER_LOCALE)?->geonameId;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public function resolve(array $row, ImportContext $context, string $kind): ?int
    {
        $geonameId = $row['city_geoname_id'] ?? null;
        if (!is_int($geonameId)) {
            return null;
        }

        $city = $this->directory->findCityByGeonameId($geonameId, self::READER_LOCALE);
        $context->count($kind, $city === null ? Outcome::Dropped : Outcome::Matched);

        return $city?->id;
    }
}
