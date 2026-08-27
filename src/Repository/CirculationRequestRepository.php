<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CirculationRequest;
use App\Entity\User;
use App\Enum\CirculationRequestStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CirculationRequest>
 */
class CirculationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CirculationRequest::class);
    }

    /**
     * @return list<CirculationRequest> oldest first - the queue order
     */
    public function findQueue(string $context, string $itemType, int $itemId): array
    {
        return array_values($this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->where('r.context = :context')->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('r.itemId = :itemId')->setParameter('itemId', $itemId)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [CirculationRequestStatus::Waiting, CirculationRequestStatus::Offered])
            ->orderBy('r.requestedAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult());
    }

    public function findOpenFor(string $context, string $itemType, int $itemId, User $user): ?CirculationRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.context = :context')->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('r.itemId = :itemId')->setParameter('itemId', $itemId)
            ->andWhere('r.user = :user')->setParameter('user', $user)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [CirculationRequestStatus::Waiting, CirculationRequestStatus::Offered])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, int> open request count keyed by item id
     */
    public function countOpenPerItem(string $context, string $itemType, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('r.itemId AS itemId, COUNT(r.id) AS total')
            ->where('r.context = :context')->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('r.itemId IN (:itemIds)')->setParameter('itemIds', $itemIds)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [CirculationRequestStatus::Waiting, CirculationRequestStatus::Offered])
            ->groupBy('r.itemId')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['itemId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<int> $itemIds
     * @return list<CirculationRequest>
     */
    public function findOpenForUserAndItems(string $context, string $itemType, array $itemIds, User $user): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values($this->createQueryBuilder('r')
            ->where('r.context = :context')->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('r.itemId IN (:itemIds)')->setParameter('itemIds', $itemIds)
            ->andWhere('r.user = :user')->setParameter('user', $user)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [CirculationRequestStatus::Waiting, CirculationRequestStatus::Offered])
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<CirculationRequest>
     */
    public function findOpenForItem(string $context, string $itemType, int $itemId): array
    {
        return $this->findQueue($context, $itemType, $itemId);
    }

    /**
     * @return list<CirculationRequest>
     */
    public function findOffersOlderThan(DateTimeImmutable $cutoff): array
    {
        return array_values($this->createQueryBuilder('r')
            ->where('r.status = :offered')->setParameter('offered', CirculationRequestStatus::Offered)
            ->andWhere('r.offeredAt < :cutoff')->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult());
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<CirculationRequest>
     */
    public function findOpenInContext(string $context, string $itemType, ?array $allowedItemIds = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->where('r.context = :context')->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [CirculationRequestStatus::Waiting, CirculationRequestStatus::Offered])
            ->orderBy('r.requestedAt', 'ASC');

        if ($allowedItemIds !== null) {
            if ($allowedItemIds === []) {
                return [];
            }
            $qb->andWhere('r.itemId IN (:allowed)')->setParameter('allowed', $allowedItemIds);
        }

        return array_values($qb->getQuery()->getResult());
    }
}
