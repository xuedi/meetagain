<?php declare(strict_types=1);

namespace Plugin\Boardgames\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;

/**
 * @extends ServiceEntityRepository<GameOwnership>
 */
class GameOwnershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameOwnership::class);
    }

    /** @return list<GameOwnership> */
    public function findShelfOf(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('g')
            ->join('o.game', 'g')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneFor(User $user, Game $game): ?GameOwnership
    {
        return $this->findOneBy(['user' => $user, 'game' => $game]);
    }

    /** @return list<GameOwnership> */
    public function findPublicOwnersOf(Game $game): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('u')
            ->join('o.user', 'u')
            ->andWhere('o.game = :game')
            ->andWhere('o.isPublic = true')
            ->setParameter('game', $game)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $gameIds
     *
     * @return list<GameOwnership>
     */
    public function findPublicOwnersOfGames(array $gameIds): array
    {
        if ($gameIds === []) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->addSelect('u')
            ->addSelect('g')
            ->join('o.user', 'u')
            ->join('o.game', 'g')
            ->andWhere('g.id IN (:ids)')
            ->andWhere('o.isPublic = true')
            ->setParameter('ids', $gameIds)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<GameOwnership> */
    public function findAskableOwnersOf(Game $game): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('u')
            ->join('o.user', 'u')
            ->andWhere('o.game = :game')
            ->andWhere('o.isPublic = true')
            ->andWhere('o.willingToBring = true')
            ->setParameter('game', $game)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $gameIds */
    public function deleteForGameIds(array $gameIds): void
    {
        if ($gameIds === []) {
            return;
        }

        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.game IN (:ids)')
            ->setParameter('ids', $gameIds)
            ->getQuery()
            ->execute();
    }
}
