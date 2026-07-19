<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\EventItemAssociation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventItemAssociation>
 */
class EventItemAssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventItemAssociation::class);
    }

    /**
     * @return EventItemAssociation[]
     */
    public function findByEvent(int $eventId): array
    {
        return $this
            ->createQueryBuilder('a')
            ->where('a.event = :eventId')
            ->setParameter('eventId', $eventId)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEventAndItem(int $eventId, string $itemType, int $itemId): ?EventItemAssociation
    {
        return $this->findOneBy(['event' => $eventId, 'itemType' => $itemType, 'itemId' => $itemId]);
    }

    /**
     * Distinct item ids currently associated to any event for the given type.
     *
     * @return list<int>
     */
    public function findItemIdsByType(string $itemType): array
    {
        $ids = $this
            ->createQueryBuilder('a')
            ->select('DISTINCT a.itemId')
            ->where('a.itemType = :type')
            ->setParameter('type', $itemType)
            ->orderBy('a.itemId', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(static fn($id): int => (int) $id, $ids));
    }
}
