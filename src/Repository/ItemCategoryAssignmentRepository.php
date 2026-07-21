<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemCategoryAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemCategoryAssignment>
 */
class ItemCategoryAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemCategoryAssignment::class);
    }

    public function findOneFor(string $itemType, int $itemId): ?ItemCategoryAssignment
    {
        return $this->findOneBy(['itemType' => $itemType, 'itemId' => $itemId]);
    }

    public function categoryFor(string $itemType, int $itemId): ?int
    {
        return $this->findOneFor($itemType, $itemId)?->getCategoryId();
    }

    /** @return list<int> */
    public function itemIdsWithCategory(string $itemType, int $categoryId): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.itemId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.categoryId = :cat')->setParameter('cat', $categoryId)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'itemId'));
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, int> item id => category id, for the items that carry one
     */
    public function categoriesForItems(string $itemType, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.itemId', 'a.categoryId')
            ->where('a.itemType = :type')->setParameter('type', $itemType)
            ->andWhere('a.itemId IN (:ids)')->setParameter('ids', $itemIds)
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['itemId']] = (int) $row['categoryId'];
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
