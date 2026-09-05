<?php declare(strict_types=1);

namespace Plugin\Boardgames\Repository;

use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\RequestStatus;

/**
 * @extends ServiceEntityRepository<BringRequest>
 */
class BringRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BringRequest::class);
    }

    public function findOneFor(Event $event, Game $game, User $requester): ?BringRequest
    {
        return $this->findOneBy(['event' => $event, 'game' => $game, 'requestedBy' => $requester]);
    }

    /** @return list<BringRequest> */
    public function findOpenForEvent(int $eventId): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g')
            ->join('r.game', 'g')
            ->andWhere('r.event = :eventId')
            ->andWhere('r.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', RequestStatus::Open)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<BringRequest> */
    public function findOpenForOwner(User $owner): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g')
            ->addSelect('e')
            ->join('r.game', 'g')
            ->join('r.event', 'e')
            ->andWhere('r.ownerUser = :owner')
            ->andWhere('r.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', RequestStatus::Open)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<BringRequest> */
    public function findOpenStartingBefore(DateTimeImmutable $moment): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.event', 'e')
            ->andWhere('r.status = :status')
            ->andWhere('e.start <= :moment')
            ->setParameter('status', RequestStatus::Open)
            ->setParameter('moment', $moment)
            ->getQuery()
            ->getResult();
    }

    /** @return list<BringRequest> */
    public function findAllOpen(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g')
            ->addSelect('e')
            ->join('r.game', 'g')
            ->join('r.event', 'e')
            ->andWhere('r.status = :status')
            ->setParameter('status', RequestStatus::Open)
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $gameIds */
    public function deleteForGameIds(array $gameIds): void
    {
        if ($gameIds === []) {
            return;
        }

        $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.game IN (:ids)')
            ->setParameter('ids', $gameIds)
            ->getQuery()
            ->execute();
    }
}
