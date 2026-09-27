<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Repository;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;

/**
 * @extends ServiceEntityRepository<Handover>
 */
class HandoverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Handover::class);
    }

    public function findOpenForCopy(Copy $copy): ?Handover
    {
        return $this->findOneBy(['copy' => $copy, 'status' => HandoverStatus::Open]);
    }

    /**
     * @return list<Handover>
     */
    public function findOpenForUser(User $user): array
    {
        return array_values(
            $this
                ->createQueryBuilder('h')
                ->where('h.status = :open')
                ->setParameter('open', HandoverStatus::Open)
                ->andWhere('h.fromUser = :user OR h.toUser = :user')
                ->setParameter('user', $user)
                ->orderBy('h.openedAt', 'DESC')
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @return list<Handover>
     */
    public function findOpenOlderThan(DateTimeImmutable $cutoff): array
    {
        return array_values(
            $this
                ->createQueryBuilder('h')
                ->where('h.status = :open')
                ->setParameter('open', HandoverStatus::Open)
                ->andWhere('h.openedAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @return list<Handover>
     */
    public function findOpenInContext(string $context, string $itemType): array
    {
        return array_values(
            $this
                ->createQueryBuilder('h')
                ->join('h.copy', 'c')
                ->where('h.status = :open')
                ->setParameter('open', HandoverStatus::Open)
                ->andWhere('c.context = :context')
                ->setParameter('context', $context)
                ->andWhere('c.itemType = :itemType')
                ->setParameter('itemType', $itemType)
                ->orderBy('h.openedAt', 'DESC')
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @return list<Handover>
     */
    public function findOpenForCopies(string $context, string $itemType, int $itemId): array
    {
        return array_values(
            $this
                ->createQueryBuilder('h')
                ->join('h.copy', 'c')
                ->where('h.status = :open')
                ->setParameter('open', HandoverStatus::Open)
                ->andWhere('c.context = :context')
                ->setParameter('context', $context)
                ->andWhere('c.itemType = :itemType')
                ->setParameter('itemType', $itemType)
                ->andWhere('c.itemId = :itemId')
                ->setParameter('itemId', $itemId)
                ->getQuery()
                ->getResult(),
        );
    }

    /**
     * @param list<int> $copyIds
     * @return list<Handover>
     */
    public function findByCopyIds(array $copyIds): array
    {
        if ($copyIds === []) {
            return [];
        }

        return array_values($this->createQueryBuilder('h')->where('h.copy IN (:copyIds)')->setParameter('copyIds', $copyIds)->getQuery()->getResult());
    }
}
