<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Repository;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Circulation\Contract\RequestStatus;
use Module\Circulation\Internal\Entity\Request;

/**
 * @extends ServiceEntityRepository<Request>
 */
class RequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Request::class);
    }

    /**
     * @return list<Request> oldest first - the queue order
     */
    public function findQueue(string $context, string $itemType, int $itemId): array
    {
        return array_values(
            $this
                ->createQueryBuilder('r')
                ->leftJoin('r.user', 'u')
                ->addSelect('u')
                ->where('r.context = :context')
                ->setParameter('context', $context)
                ->andWhere('r.itemType = :itemType')
                ->setParameter('itemType', $itemType)
                ->andWhere('r.itemId = :itemId')
                ->setParameter('itemId', $itemId)
                ->andWhere('r.status IN (:open)')
                ->setParameter('open', [RequestStatus::Waiting, RequestStatus::Offered])
                ->orderBy('r.requestedAt', 'ASC')
                ->addOrderBy('r.id', 'ASC')
                ->getQuery()
                ->getResult(),
        );
    }

    public function findOpenFor(string $context, string $itemType, int $itemId, User $user): ?Request
    {
        return $this
            ->createQueryBuilder('r')
            ->where('r.context = :context')
            ->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')
            ->setParameter('itemType', $itemType)
            ->andWhere('r.itemId = :itemId')
            ->setParameter('itemId', $itemId)
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [RequestStatus::Waiting, RequestStatus::Offered])
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

        $rows = $this
            ->createQueryBuilder('r')
            ->select('r.itemId AS itemId, COUNT(r.id) AS total')
            ->where('r.context = :context')
            ->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')
            ->setParameter('itemType', $itemType)
            ->andWhere('r.itemId IN (:itemIds)')
            ->setParameter('itemIds', $itemIds)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [RequestStatus::Waiting, RequestStatus::Offered])
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
     * @return list<Request>
     */
    public function findOpenForUserAndItems(string $context, string $itemType, array $itemIds, User $user): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values(
            $this
                ->createQueryBuilder('r')
                ->where('r.context = :context')
                ->setParameter('context', $context)
                ->andWhere('r.itemType = :itemType')
                ->setParameter('itemType', $itemType)
                ->andWhere('r.itemId IN (:itemIds)')
                ->setParameter('itemIds', $itemIds)
                ->andWhere('r.user = :user')
                ->setParameter('user', $user)
                ->andWhere('r.status IN (:open)')
                ->setParameter('open', [RequestStatus::Waiting, RequestStatus::Offered])
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @return list<Request>
     */
    public function findOpenForItem(string $context, string $itemType, int $itemId): array
    {
        return $this->findQueue($context, $itemType, $itemId);
    }

    /**
     * @return list<Request>
     */
    public function findOffersOlderThan(DateTimeImmutable $cutoff): array
    {
        return array_values(
            $this
                ->createQueryBuilder('r')
                ->where('r.status = :offered')
                ->setParameter('offered', RequestStatus::Offered)
                ->andWhere('r.offeredAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @param list<int>|null $allowedItemIds
     * @return list<Request>
     */
    public function findOpenInContext(string $context, string $itemType, ?array $allowedItemIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->where('r.context = :context')
            ->setParameter('context', $context)
            ->andWhere('r.itemType = :itemType')
            ->setParameter('itemType', $itemType)
            ->andWhere('r.status IN (:open)')
            ->setParameter('open', [RequestStatus::Waiting, RequestStatus::Offered])
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
