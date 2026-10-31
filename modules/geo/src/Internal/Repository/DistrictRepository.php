<?php declare(strict_types=1);

namespace Module\Geo\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Geo\Internal\Entity\District;

/**
 * @extends ServiceEntityRepository<District>
 */
class DistrictRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, District::class);
    }

    /**
     * @param list<int> $geonameIds
     * @return array<int, District> keyed by GeoNames id
     */
    public function findByGeonameIds(array $geonameIds): array
    {
        if ($geonameIds === []) {
            return [];
        }
        $byGeonameId = [];
        foreach ($this->findBy(['geonameId' => $geonameIds]) as $district) {
            $byGeonameId[(int) $district->getGeonameId()] = $district;
        }

        return $byGeonameId;
    }

    /**
     * @return array<int, array<string, true>> taken slugs keyed by city id
     */
    public function findSlugsByCity(): array
    {
        $rows = $this->createQueryBuilder('d')->select('IDENTITY(d.city) AS cityId', 'd.slug')->getQuery()->getArrayResult();
        $slugs = [];
        foreach ($rows as $row) {
            $slugs[(int) $row['cityId']][(string) $row['slug']] = true;
        }

        return $slugs;
    }

    /**
     * @return array<int, int> district id keyed by GeoNames id
     */
    public function findIdsOfCountry(int $countryId): array
    {
        $rows = $this
            ->createQueryBuilder('d')
            ->select('d.id', 'd.geonameId')
            ->join('d.city', 'c')
            ->where('c.country = :country')
            ->setParameter('country', $countryId)
            ->andWhere('d.geonameId IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'id', 'geonameId'));
    }
}
