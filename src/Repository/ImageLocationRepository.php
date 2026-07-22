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
     * Returns all (imageId, locationId) pairs currently stored for the given type.
     *
     * @return array<array{imageId: int, locationId: int}>
     */
    public function findPairsByType(ImageType $type): array
    {
        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative('SELECT image_id, location_id FROM image_location WHERE location_type = ?', [
                $type->value,
            ]);

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    /**
     * Returns the distinct image IDs stored at a given location for any of the given types.
     * Resolves images owned directly by an entity, where that entity's own ID is stored as the
     * location_id (as opposed to images owned indirectly through other content).
     *
     * @param list<ImageType> $types
     * @return array<int>
     */
    public function findImageIdsByTypesAndLocationId(array $types, int $locationId): array
    {
        if ($types === []) {
            return [];
        }

        $typeValues = array_map(static fn(ImageType $type) => $type->value, $types);
        $placeholders = implode(', ', array_fill(0, count($typeValues), '?'));

        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn(
                "SELECT DISTINCT image_id FROM image_location WHERE location_id = ? AND location_type IN ({$placeholders})",
                array_merge([$locationId], $typeValues),
            );

        return array_map('intval', $rows);
    }

    /**
     * Reverse of findImageIdsByTypesAndLocationId: for each given image, the location IDs it is
     * stored at for any of the given types. Used to resolve directly-owned images back to the
     * entity IDs stored as their location_id.
     *
     * @param list<int>       $imageIds
     * @param list<ImageType> $types
     * @return array<int, list<int>> imageId => location IDs
     */
    public function findLocationIdsByImageIdsAndTypes(array $imageIds, array $types): array
    {
        if ($imageIds === [] || $types === []) {
            return [];
        }

        $typeValues = array_map(static fn(ImageType $type) => $type->value, $types);
        $imagePlaceholders = implode(', ', array_fill(0, count($imageIds), '?'));
        $typePlaceholders = implode(', ', array_fill(0, count($typeValues), '?'));

        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                "SELECT DISTINCT image_id, location_id FROM image_location
                 WHERE image_id IN ({$imagePlaceholders}) AND location_type IN ({$typePlaceholders})",
                array_merge($imageIds, $typeValues),
            );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['image_id']][] = (int) $row['location_id'];
        }

        return $result;
    }

    /**
     * Bulk-delete rows matching the given type and (imageId, locationId) pairs.
     *
     * @param array<array{imageId: int, locationId: int}> $pairs
     */
    public function deleteByTypeAndPairs(ImageType $type, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $conn = $this->getEntityManager()->getConnection();
        foreach ($pairs as $pair) {
            $conn->executeStatement('DELETE FROM image_location WHERE location_type = ? AND image_id = ? AND location_id = ?', [
                $type->value,
                $pair['imageId'],
                $pair['locationId'],
            ]);
        }
    }

    /**
     * Bulk-insert (imageId, locationId) pairs for the given type.
     * Silently skips rows that already exist (via INSERT IGNORE on the unique constraint).
     *
     * @param array<array{imageId: int, locationId: int}> $pairs
     */
    public function insertForType(ImageType $type, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $conn = $this->getEntityManager()->getConnection();
        foreach ($pairs as $pair) {
            $conn->executeStatement('INSERT IGNORE INTO image_location (image_id, location_type, location_id) VALUES (?, ?, ?)', [
                $pair['imageId'],
                $type->value,
                $pair['locationId'],
            ]);
        }
    }

    /**
     * Returns a map of imageId → location row count for use in list views.
     *
     * @return array<int, int>
     */
    public function countPerImageId(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT image_id, COUNT(*) AS cnt FROM image_location GROUP BY image_id');

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['image_id']] = (int) $row['cnt'];
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
