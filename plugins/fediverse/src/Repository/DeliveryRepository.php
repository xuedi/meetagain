<?php declare(strict_types=1);

namespace Plugin\Fediverse\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Fediverse\Entity\Delivery;

/**
 * @extends ServiceEntityRepository<Delivery>
 */
class DeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delivery::class);
    }

    /** @return list<Delivery> */
    public function claimDue(int $budget, DateTimeImmutable $now, DateTimeImmutable $leaseExpiredBefore): array
    {
        $candidates = array_map(
            intval(...),
            $this
                ->claimable($now, $leaseExpiredBefore)
                ->select('d.id')
                ->orderBy('d.dueAt')
                ->setMaxResults($budget)
                ->getQuery()
                ->getSingleColumnResult(),
        );
        if ($candidates === []) {
            return [];
        }

        $token = bin2hex(random_bytes(16));
        $this
            ->claimable($now, $leaseExpiredBefore)
            ->update()
            ->set('d.claimToken', ':token')
            ->set('d.claimedAt', ':now')
            ->andWhere('d.id IN (:candidates)')
            ->setParameter('token', $token)
            ->setParameter('candidates', $candidates)
            ->getQuery()
            ->execute();

        return $this->findBy(['claimToken' => $token]);
    }

    public function countPending(): int
    {
        return $this->count([]);
    }

    private function claimable(DateTimeImmutable $now, DateTimeImmutable $leaseExpiredBefore): QueryBuilder
    {
        return $this
            ->createQueryBuilder('d')
            ->where('d.dueAt <= :now')
            ->andWhere('d.claimedAt IS NULL OR d.claimedAt < :leaseExpiredBefore')
            ->setParameter('now', $now)
            ->setParameter('leaseExpiredBefore', $leaseExpiredBefore);
    }
}
