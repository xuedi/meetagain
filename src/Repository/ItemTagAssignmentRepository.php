<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemTagAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemTagAssignment>
 */
class ItemTagAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemTagAssignment::class);
    }

    /** @return list<ItemTagAssignment> */
    public function findFor(string $itemType, int $itemId): array
    {
        return array_values($this->findBy(['itemType' => $itemType, 'itemId' => $itemId]));
    }

    /** @return list<int> */
    public function tagIdsFor(string $itemType, int $itemId): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.tag) AS tagId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId = :id')->setParameter('id', $itemId)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'tagId'));
    }

    /**
     * @param list<int> $tagIds
     * @return list<int>
     */
    public function itemIdsWithAllTags(string $itemType, array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.itemId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.tag IN (:tags)')->setParameter('tags', $tagIds)
            ->groupBy('a.itemId')
            ->having('COUNT(DISTINCT a.tag) = :count')->setParameter('count', count($tagIds))
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'itemId'));
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, int> tag id => how many of the given items carry it
     */
    public function countsByTag(string $itemType, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.tag) AS tagId', 'COUNT(a.itemId) AS total')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId IN (:ids)')->setParameter('ids', $itemIds)
            ->groupBy('a.tag')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['tagId']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<int, int> tag id => how many items of this type carry it */
    public function countsForType(string $itemType): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.tag) AS tagId', 'COUNT(a.itemId) AS total')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->groupBy('a.tag')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['tagId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, list<int>> item id => tag ids, for the items that carry any
     */
    public function tagIdsForItems(string $itemType, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.itemId', 'IDENTITY(a.tag) AS tagId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId IN (:ids)')->setParameter('ids', $itemIds)
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['itemId']][] = (int) $row['tagId'];
        }

        return $result;
    }

    /** @param list<int> $tagIds */
    public function deleteForTags(string $itemType, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $this->createQueryBuilder('a')
            ->delete()
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.tag IN (:tags)')->setParameter('tags', $tagIds)
            ->getQuery()
            ->execute();
    }

    public function deleteFor(string $itemType, int $itemId): void
    {
        $this->createQueryBuilder('a')
            ->delete()
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId = :id')->setParameter('id', $itemId)
            ->getQuery()
            ->execute();
    }
}
