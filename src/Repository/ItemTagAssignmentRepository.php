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
            ->select('a.tagId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId = :id')->setParameter('id', $itemId)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'tagId'));
    }

    /**
     * Item ids carrying every one of the given tags (AND-intersection), for the tag-cloud filter.
     *
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
            ->andWhere('a.tagId IN (:tags)')->setParameter('tags', $tagIds)
            ->groupBy('a.itemId')
            ->having('COUNT(DISTINCT a.tagId) = :count')->setParameter('count', count($tagIds))
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'itemId'));
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
            ->select('a.itemId', 'a.tagId')
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
