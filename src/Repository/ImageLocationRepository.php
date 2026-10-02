<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImageLocation;
use App\Enum\ImageType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ImageLocation> */
class ImageLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageLocation::class);
    }

    /**
     * @return array<array{imageId: int, locationId: int}>
     */
    public function findPairsByType(ImageType $type): array
    {
        $rows = $this
            ->createQueryBuilder('il')
            ->select('IDENTITY(il.image) AS imageId', 'il.locationId')
            ->where('il.locationType = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['imageId'],
            'locationId' => (int) $r['locationId'],
        ], $rows);
    }

    /**
     * @param list<ImageType> $types
     * @return array<int>
     */
    public function findImageIdsByTypesAndLocationId(array $types, int $locationId): array
    {
        if ($types === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('il')
            ->select('DISTINCT IDENTITY(il.image) AS imageId')
            ->where('il.locationId = :locationId')
            ->andWhere('il.locationType IN (:types)')
            ->setParameter('locationId', $locationId)
            ->setParameter('types', $types)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }

    /**
     * @param list<int>       $imageIds
     * @param list<ImageType> $types
     * @return array<int, list<int>> imageId => location IDs
     */
    public function findLocationIdsByImageIdsAndTypes(array $imageIds, array $types): array
    {
        if ($imageIds === [] || $types === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('il')
            ->select('DISTINCT IDENTITY(il.image) AS imageId', 'il.locationId')
            ->where('il.image IN (:imageIds)')
            ->andWhere('il.locationType IN (:types)')
            ->setParameter('imageIds', $imageIds)
            ->setParameter('types', $types)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['imageId']][] = (int) $row['locationId'];
        }

        return $result;
    }

    /**
     * @return array<int, list<ImageType>> imageId => location types, ascending by type value
     */
    public function findTypesPerImageId(): array
    {
        $rows = $this
            ->createQueryBuilder('il')
            ->select('DISTINCT IDENTITY(il.image) AS imageId', 'il.locationType')
            ->orderBy('imageId')
            ->addOrderBy('il.locationType')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['imageId']][] = $row['locationType'];
        }

        return $result;
    }

    /**
     * @param array<array{imageId: int, locationId: int}> $pairs
     */
    public function deleteByTypeAndPairs(ImageType $type, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $query = $this
            ->createQueryBuilder('il')
            ->delete()
            ->where('il.locationType = :type')
            ->andWhere('il.image = :imageId')
            ->andWhere('il.locationId = :locationId')
            ->setParameter('type', $type)
            ->getQuery();
        foreach ($pairs as $pair) {
            $query->setParameter('imageId', $pair['imageId'])->setParameter('locationId', $pair['locationId'])->execute();
        }
    }

    /**
     * @param array<array{imageId: int, locationId: int}> $pairs
     */
    public function insertForType(ImageType $type, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $insertIgnore = $this->getEntityManager()->getConnection();
        foreach ($pairs as $pair) {
            $insertIgnore->executeStatement('INSERT IGNORE INTO image_location (image_id, location_type, location_id) VALUES (?, ?, ?)', [
                $pair['imageId'],
                $type->value,
                $pair['locationId'],
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    public function countPerImageId(): array
    {
        $rows = $this
            ->createQueryBuilder('il')
            ->select('IDENTITY(il.image) AS imageId', 'COUNT(il.id) AS cnt')
            ->groupBy('il.image')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['imageId']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * @return ImageLocation[]
     */
    public function findByImageId(int $imageId): array
    {
        return $this->createQueryBuilder('il')->where('il.image = :imageId')->setParameter('imageId', $imageId)->getQuery()->getResult();
    }
}
