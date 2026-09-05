<?php declare(strict_types=1);

namespace Plugin\Boardgames\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\PledgeStatus;

/**
 * @extends ServiceEntityRepository<GamePledge>
 */
class GamePledgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GamePledge::class);
    }

    /** @return list<GamePledge> */
    public function findActiveForEvent(int $eventId): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('g')
            ->addSelect('u')
            ->join('p.game', 'g')
            ->join('p.user', 'u')
            ->andWhere('p.event = :eventId')
            ->andWhere('p.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', PledgeStatus::Pledged)
            ->orderBy('u.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveForEvent(int $eventId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.event = :eventId')
            ->andWhere('p.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', PledgeStatus::Pledged)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneFor(Event $event, Game $game, User $user): ?GamePledge
    {
        return $this->findOneBy(['event' => $event, 'game' => $game, 'user' => $user]);
    }

    /** @param list<int> $gameIds */
    public function deleteForGameIds(array $gameIds): void
    {
        if ($gameIds === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.game IN (:ids)')
            ->setParameter('ids', $gameIds)
            ->getQuery()
            ->execute();
    }
}
