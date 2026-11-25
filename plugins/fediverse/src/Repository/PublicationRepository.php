<?php declare(strict_types=1);

namespace Plugin\Fediverse\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Publication;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function findLiveForEvent(int $eventId): ?Publication
    {
        return $this->findOneBy(['eventId' => $eventId, 'deletedAt' => null]);
    }

    /** @return list<Publication> newest first */
    public function findLiveFor(Account $account, int $limit): array
    {
        return $this->findBy(['account' => $account, 'deletedAt' => null], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function countLiveFor(Account $account): int
    {
        return $this->count(['account' => $account, 'deletedAt' => null]);
    }

    /** @return list<int> */
    public function findLiveEventIdsFor(Account $account): array
    {
        return array_map(
            intval(...),
            $this
                ->createQueryBuilder('p')
                ->select('p.eventId')
                ->where('p.account = :account')
                ->andWhere('p.deletedAt IS NULL')
                ->setParameter('account', $account)
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }

    /** @return list<int> */
    public function findLiveEventIdsStartingFrom(DateTimeImmutable $from): array
    {
        return array_map(
            intval(...),
            $this
                ->createQueryBuilder('p')
                ->select('p.eventId')
                ->where('p.deletedAt IS NULL')
                ->andWhere('p.eventStartsAt >= :from')
                ->setParameter('from', $from)
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }
}
