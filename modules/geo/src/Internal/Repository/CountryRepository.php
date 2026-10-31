<?php declare(strict_types=1);

namespace Module\Geo\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Entity\CountryTranslation;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    public function findOneByIso2(string $iso2): ?Country
    {
        return $this->findOneBy(['iso2' => strtoupper($iso2)]);
    }

    public function findOneByNameSlug(string $nameSlug): ?Country
    {
        $result = $this
            ->createQueryBuilder('co')
            ->leftJoin('co.translations', 't')
            ->where('co.nameSlug = :slug OR t.nameSlug = :slug')
            ->setParameter('slug', $nameSlug)
            ->orderBy('co.population', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * @param list<int> $countryIds
     * @param list<string> $languages
     * @return array<int, array<string, string>> name per language, keyed by country id
     */
    public function findTranslatedNames(array $countryIds, array $languages): array
    {
        if ($countryIds === []) {
            return [];
        }

        $rows = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(t.country) AS parentId', 't.language', 't.name')
            ->from(CountryTranslation::class, 't')
            ->where('t.country IN (:ids)')
            ->setParameter('ids', $countryIds)
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

    public function hasAny(): bool
    {
        return $this->createQueryBuilder('co')->select('co.id')->setMaxResults(1)->getQuery()->getArrayResult() !== [];
    }

    /**
     * @return array<string, Country> keyed by ISO2
     */
    public function findAllByIso2(): array
    {
        $byIso2 = [];
        foreach ($this->findAll() as $country) {
            $byIso2[$country->getIso2()] = $country;
        }

        return $byIso2;
    }
}
