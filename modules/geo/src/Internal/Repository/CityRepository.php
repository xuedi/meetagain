<?php declare(strict_types=1);

namespace Module\Geo\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\CityTranslation;
use Module\Geo\Internal\Entity\Country;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    public function hasAny(): bool
    {
        $found = $this->createQueryBuilder('c')->select('c.id')->setMaxResults(1)->getQuery()->getArrayResult();

        return $found !== [];
    }

    /**
     * @param list<int> $cityIds
     * @return list<City>
     */
    public function findWithCountry(array $cityIds): array
    {
        if ($cityIds === []) {
            return [];
        }

        return array_values($this->withCountry()->where('c.id IN (:ids)')->setParameter('ids', $cityIds)->getQuery()->getResult());
    }

    public function findOneBySlug(string $countryIso2, string $slug): ?City
    {
        $result = $this
            ->withCountry()
            ->where('co.iso2 = :iso2')
            ->setParameter('iso2', strtoupper($countryIso2))
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    public function findOneByGeonameId(int $geonameId): ?City
    {
        $result = $this->withCountry()->where('c.geonameId = :geonameId')->setParameter('geonameId', $geonameId)->getQuery()->getResult();

        return $result[0] ?? null;
    }

    public function findMostPopulousByNameSlug(string $nameSlug): ?City
    {
        $result = $this
            ->withCountry()
            ->leftJoin('c.translations', 't')
            ->where('c.nameSlug = :slug OR t.nameSlug = :slug')
            ->setParameter('slug', $nameSlug)
            ->orderBy('c.population', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * @return list<int> most populous first
     */
    public function findIdsByNameSlug(string $nameSlug): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('DISTINCT c.id', 'c.population')
            ->leftJoin('c.translations', 't')
            ->where('c.nameSlug = :slug OR t.nameSlug = :slug')
            ->setParameter('slug', $nameSlug)
            ->orderBy('c.population', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'id'));
    }

    /**
     * @return list<int>
     */
    public function searchIds(string $name, string $nameSlug, ?string $countryIso2, int $limit): array
    {
        $qb = $this
            ->createQueryBuilder('c')
            ->select('DISTINCT c.id', 'c.population')
            ->leftJoin('c.translations', 't')
            ->where('t.name LIKE :name')
            ->setParameter('name', addcslashes($name, '%_\\') . '%')
            ->orderBy('c.population', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit);

        if ($nameSlug !== '') {
            $qb->orWhere('c.nameSlug LIKE :slug OR t.nameSlug LIKE :slug')->setParameter('slug', $nameSlug . '%');
        }
        if ($countryIso2 !== null) {
            $qb->join('c.country', 'co')->andWhere('co.iso2 = :iso2')->setParameter('iso2', strtoupper($countryIso2));
        }

        return array_map(intval(...), array_column($qb->getQuery()->getArrayResult(), 'id'));
    }

    /**
     * @param list<int> $cityIds
     * @param list<string> $languages
     * @return array<int, array<string, string>> name per language, keyed by city id
     */
    public function findTranslatedNames(array $cityIds, array $languages): array
    {
        if ($cityIds === []) {
            return [];
        }

        $rows = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(t.city) AS parentId', 't.language', 't.name')
            ->from(CityTranslation::class, 't')
            ->where('t.city IN (:ids)')
            ->setParameter('ids', $cityIds)
            ->andWhere('t.language IN (:languages)')
            ->setParameter('languages', $languages)
            ->getQuery()
            ->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['parentId']][(string) $row['language']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * @param list<int> $cityIds
     * @return array<int, string> country ISO2 keyed by city id
     */
    public function findCountryCodes(array $cityIds): array
    {
        if ($cityIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('c')
            ->select('c.id', 'co.iso2')
            ->join('c.country', 'co')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $cityIds)
            ->getQuery()
            ->getArrayResult();

        return array_map(strval(...), array_column($rows, 'iso2', 'id'));
    }

    /**
     * @return list<string>
     */
    public function findSlugsStartingWith(Country $country, string $slug): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('c.slug')
            ->where('c.country = :country')
            ->setParameter('country', $country)
            ->andWhere('c.slug = :slug OR c.slug LIKE :prefix')
            ->setParameter('slug', $slug)
            ->setParameter('prefix', addcslashes($slug, '%_\\') . '-%')
            ->getQuery()
            ->getArrayResult();

        return array_map(strval(...), array_column($rows, 'slug'));
    }

    private function withCountry(): QueryBuilder
    {
        return $this->createQueryBuilder('c')->join('c.country', 'co')->addSelect('co');
    }

    /**
     * @param list<int> $geonameIds
     * @return array<int, City> keyed by GeoNames id
     */
    public function findByGeonameIds(array $geonameIds): array
    {
        if ($geonameIds === []) {
            return [];
        }
        $byGeonameId = [];
        foreach ($this->findBy(['geonameId' => $geonameIds]) as $city) {
            $byGeonameId[$city->getGeonameId()] = $city;
        }

        return $byGeonameId;
    }

    /**
     * @return array<string, true>
     */
    public function findSlugsOfCountry(int $countryId): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('c.slug')
            ->where('c.country = :country')
            ->setParameter('country', $countryId)
            ->getQuery()
            ->getArrayResult();

        return array_fill_keys(array_map(strval(...), array_column($rows, 'slug')), true);
    }

    /**
     * @return array<int, int> city id keyed by GeoNames id
     */
    public function findIdsOfCountry(int $countryId): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('c.id', 'c.geonameId')
            ->where('c.country = :country')
            ->setParameter('country', $countryId)
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'id', 'geonameId'));
    }

    /**
     * @return list<array{id: int, latitude: float, longitude: float, population: int}>
     */
    public function findPointsOfState(int $countryId, ?int $stateId): array
    {
        $qb = $this
            ->createQueryBuilder('c')
            ->select('c.id', 'c.latitude', 'c.longitude', 'c.population')
            ->where('c.country = :country')
            ->setParameter('country', $countryId);
        $stateId === null ? $qb->andWhere('c.state IS NULL') : $qb->andWhere('c.state = :state')->setParameter('state', $stateId);

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'population' => (int) $row['population'],
        ], $qb->getQuery()->getArrayResult());
    }

    /**
     * @return array{north: string, south: string, east: string, west: string}|null
     */
    public function findExtent(int $countryId): ?array
    {
        $row = $this
            ->createQueryBuilder('c')
            ->select('MAX(c.latitude) AS north', 'MIN(c.latitude) AS south', 'MAX(c.longitude) AS east', 'MIN(c.longitude) AS west')
            ->where('c.country = :country')
            ->setParameter('country', $countryId)
            ->getQuery()
            ->getSingleResult();

        return $row['north'] === null ? null : array_map(strval(...), $row);
    }

    public function findCapitalOrLargest(int $countryId): ?City
    {
        $result = $this
            ->createQueryBuilder('c')
            ->addSelect("CASE WHEN c.featureCode = 'PPLC' THEN 0 ELSE 1 END AS HIDDEN capitalFirst")
            ->where('c.country = :country')
            ->setParameter('country', $countryId)
            ->orderBy('capitalFirst', 'ASC')
            ->addOrderBy('c.population', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function findCountriesWithCities(): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('DISTINCT co.iso2')
            ->join('c.country', 'co')
            ->orderBy('co.iso2', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(strval(...), array_column($rows, 'iso2'));
    }

    /**
     * @return array<string, int> keyed by country ISO2
     */
    public function countPerCountry(): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('co.iso2 AS iso2', 'COUNT(c.id) AS cities')
            ->join('c.country', 'co')
            ->groupBy('co.iso2')
            ->orderBy('co.iso2', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['iso2']] = (int) $row['cities'];
        }

        return $counts;
    }
}
