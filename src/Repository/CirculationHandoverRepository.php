<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\User;
use App\Enum\CirculationHandoverStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CirculationHandover>
 */
class CirculationHandoverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CirculationHandover::class);
    }

    public function findOpenForCopy(CirculationCopy $copy): ?CirculationHandover
    {
        return $this->findOneBy(['copy' => $copy, 'status' => CirculationHandoverStatus::Open]);
    }

    /**
     * @return list<CirculationHandover>
     */
    public function findOpenForUser(User $user): array
    {
        return array_values($this->createQueryBuilder('h')
            ->where('h.status = :open')->setParameter('open', CirculationHandoverStatus::Open)
            ->andWhere('h.fromUser = :user OR h.toUser = :user')->setParameter('user', $user)
            ->orderBy('h.openedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<CirculationHandover>
     */
    public function findOpenOlderThan(DateTimeImmutable $cutoff): array
    {
        return array_values($this->createQueryBuilder('h')
            ->where('h.status = :open')->setParameter('open', CirculationHandoverStatus::Open)
            ->andWhere('h.openedAt < :cutoff')->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<CirculationHandover>
     */
    public function findOpenInContext(string $context, string $itemType): array
    {
        return array_values($this->createQueryBuilder('h')
            ->join('h.copy', 'c')
            ->where('h.status = :open')->setParameter('open', CirculationHandoverStatus::Open)
            ->andWhere('c.context = :context')->setParameter('context', $context)
            ->andWhere('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->orderBy('h.openedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @return list<CirculationHandover>
     */
    public function findOpenForCopies(string $context, string $itemType, int $itemId): array
    {
        return array_values($this->createQueryBuilder('h')
            ->join('h.copy', 'c')
            ->where('h.status = :open')->setParameter('open', CirculationHandoverStatus::Open)
            ->andWhere('c.context = :context')->setParameter('context', $context)
            ->andWhere('c.itemType = :itemType')->setParameter('itemType', $itemType)
            ->andWhere('c.itemId = :itemId')->setParameter('itemId', $itemId)
            ->getQuery()
            ->getResult());
    }

    /**
     * @param list<int> $copyIds
     * @return list<CirculationHandover>
     */
    public function findByCopyIds(array $copyIds): array
    {
        if ($copyIds === []) {
            return [];
        }

        return array_values($this->createQueryBuilder('h')
            ->where('h.copy IN (:copyIds)')->setParameter('copyIds', $copyIds)
            ->getQuery()
            ->getResult());
    }
}
