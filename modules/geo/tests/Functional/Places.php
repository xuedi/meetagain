<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\CityTranslation;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Entity\CountryTranslation;
use Module\Geo\Internal\Slugs;

final readonly class Places
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Slugs $slugs,
    ) {}

    /**
     * @param array<string, string> $names
     */
    public function country(string $iso2, string $iso3, int $geonameId, string $name, int $population, array $names = []): Country
    {
        $country = new Country($geonameId, $iso2, $iso3, $name, $this->slugs->of($name))->setPopulation($population);
        $this->entityManager->persist($country);
        foreach ($names as $language => $translated) {
            $this->entityManager->persist(new CountryTranslation($country, $language, $translated, $this->slugs->of($translated)));
        }
        $this->entityManager->flush();

        return $country;
    }

    /**
     * @param array<string, string> $names
     */
    public function city(Country $country, int $geonameId, string $asciiName, int $population, array $names = []): City
    {
        $nameSlug = $this->slugs->of($asciiName);
        $city = new City(
            $country,
            $geonameId,
            $asciiName,
            $this->slugs->freeCitySlug($country, $nameSlug),
            $nameSlug,
            '52.5243700',
            '13.4105300',
            'Europe/Berlin',
            $population,
            'PPL',
        );
        $this->entityManager->persist($city);
        foreach ($names as $language => $translated) {
            $this->entityManager->persist(new CityTranslation($city, $language, $translated, $this->slugs->of($translated)));
        }
        $this->entityManager->flush();

        return $city;
    }
}
