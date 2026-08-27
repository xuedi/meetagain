<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CirculationCopy;
use App\Enum\CirculationCopyStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CirculationCopy>
 */
class CirculationCopyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CirculationCopy::class);
    }

    /**
     * @param list<int>|null $allowedItemIds null = no restriction, [] = nothing visible
     * @return list<CirculationCopy>
     */
    public function findForItem(string $context, string $itemType, int $itemId, ?array $allowedItemIds = null): array
    {
        if ($allowedItemIds !== null && !in_array($itemId, $allowedItemIds, true)) {
            return [];
        }

        return array_values($this->circulatingQuery($context, $itemType)
            ->andWhere('c.itemId = :itemId')->setParameter('itemId', $itemId)
            ->orderBy('c.donatedAt', 'ASC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @param list<int> $itemIds
     * @return list<CirculationCopy>
     */
    public function findForItems(string $context, string $itemType, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values($this->circulatingQuery($context, $itemType)
            ->andWhere('c.itemId IN (:itemIds)')->setParameter('itemIds', $itemIds)
            ->orderBy('c.donatedAt', 'ASC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<CirculationCopy>
     */
    public function findShelf(string $context, string $itemType, ?array $allowedItemIds = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.holder', 'h')->addSelect('h')
            ->leftJoin('c.donatedBy', 'd')->addSelect('d')
            ->where('c.context = :context')->setParameter('context', $context)
            ->andWhere('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->orderBy('c.donatedAt', 'DESC');

        if ($allowedItemIds !== null) {
            if ($allowedItemIds === []) {
                return [];
            }
            $qb->andWhere('c.itemId IN (:allowed)')->setParameter('allowed', $allowedItemIds);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<CirculationCopy>
     */
    public function findAvailableForItem(string $context, string $itemType, int $itemId): array
    {
        return array_values($this->createQueryBuilder('c')
            ->where('c.context = :context')->setParameter('context', $context)
            ->andWhere('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('c.itemId = :itemId')->setParameter('itemId', $itemId)
            ->andWhere('c.status = :status')->setParameter('status', CirculationCopyStatus::Available)
            ->orderBy('c.heldSince', 'ASC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<CirculationCopy>
     */
    public function findByItem(string $itemType, int $itemId): array
    {
        return array_values($this->findBy(['itemType' => $itemType, 'itemId' => $itemId]));
    }

    /**
     * @return list<CirculationCopy>
     */
    public function findAllOrdered(?string $context = null): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.id', 'ASC');
        if ($context !== null) {
            $qb->where('c.context = :context')->setParameter('context', $context);
        }

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return list<string>
     */
    public function findDistinctContexts(string $itemType): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.context AS context')
            ->where('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->getQuery()
            ->getScalarResult();

        return array_map('strval', array_column($rows, 'context'));
    }

    /**
     * @return list<CirculationCopy>
     */
    public function findHeldBy(int $userId): array
    {
        return array_values($this->createQueryBuilder('c')
            ->where('c.holder = :userId')->setParameter('userId', $userId)
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [CirculationCopyStatus::Available, CirculationCopyStatus::Held])
            ->getQuery()
            ->getResult());
    }

    private function circulatingQuery(string $context, string $itemType): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.holder', 'h')->addSelect('h')
            ->leftJoin('c.donatedBy', 'd')->addSelect('d')
            ->where('c.context = :context')->setParameter('context', $context)
            ->andWhere('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [
                CirculationCopyStatus::Available,
                CirculationCopyStatus::Held,
                CirculationCopyStatus::InHandover,
            ]);
    }
}
