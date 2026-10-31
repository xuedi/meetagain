<?php declare(strict_types=1);

namespace Module\Geo\Internal;

use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Repository\CityRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class Slugs
{
    public function __construct(
        private SluggerInterface $slugger,
        private CityRepository $cities,
    ) {}

    public function of(string $name): string
    {
        return $this->slugger->slug($name)->lower()->toString();
    }

    public function freeCitySlug(Country $country, string $nameSlug): string
    {
        return $this->nextFree($nameSlug, array_flip($this->cities->findSlugsStartingWith($country, $nameSlug)));
    }

    /**
     * @param array<string, mixed> $taken keyed by slug
     */
    public function nextFree(string $nameSlug, array $taken): string
    {
        if (!array_key_exists($nameSlug, $taken)) {
            return $nameSlug;
        }

        $suffix = 2;
        while (array_key_exists($nameSlug . '-' . $suffix, $taken)) {
            ++$suffix;
        }

        return $nameSlug . '-' . $suffix;
    }
}
