<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Repository\GameOwnershipRepository;

class ShelfService
{
    /** @var array<int, list<GameOwnership>> */
    private array $publicOwnerMemo = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameOwnershipRepository $ownershipRepo,
    ) {}

    /** @return list<GameOwnership> */
    public function getShelf(User $user): array
    {
        return $this->ownershipRepo->findShelfOf($user);
    }

    public function getOwnership(User $user, Game $game): ?GameOwnership
    {
        return $this->ownershipRepo->findOneFor($user, $game);
    }

    public function add(User $user, Game $game): GameOwnership
    {
        $existing = $this->ownershipRepo->findOneFor($user, $game);
        if ($existing !== null) {
            return $existing;
        }

        $ownership = new GameOwnership();
        $ownership->setUser($user);
        $ownership->setGame($game);
        $ownership->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($ownership);
        $this->em->flush();
        $this->publicOwnerMemo = [];

        return $ownership;
    }

    public function save(GameOwnership $ownership): void
    {
        $this->em->persist($ownership);
        $this->em->flush();
        $this->publicOwnerMemo = [];
    }

    public function remove(GameOwnership $ownership): void
    {
        $this->em->remove($ownership);
        $this->em->flush();
        $this->publicOwnerMemo = [];
    }

    /**
     * @param list<int> $gameIds
     */
    public function warmPublicOwners(array $gameIds): void
    {
        $missing = array_values(array_filter($gameIds, fn(int $id): bool => !array_key_exists($id, $this->publicOwnerMemo)));
        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->publicOwnerMemo[$id] = [];
        }

        foreach ($this->ownershipRepo->findPublicOwnersOfGames($missing) as $ownership) {
            $this->publicOwnerMemo[(int) $ownership->getGame()?->getId()][] = $ownership;
        }
    }

    /** @return list<GameOwnership> */
    public function getPublicOwners(Game $game): array
    {
        $gameId = (int) $game->getId();
        if (!array_key_exists($gameId, $this->publicOwnerMemo)) {
            $this->publicOwnerMemo[$gameId] = $this->ownershipRepo->findPublicOwnersOf($game);
        }

        return $this->publicOwnerMemo[$gameId];
    }

    /** @return list<GameOwnership> */
    public function getAskableOwners(Game $game): array
    {
        return $this->ownershipRepo->findAskableOwnersOf($game);
    }

    /** @return list<GameOwnership> */
    public function getBringableShelf(User $user): array
    {
        return array_values(array_filter(
            $this->getShelf($user),
            static fn(GameOwnership $ownership): bool => $ownership->isWillingToBring(),
        ));
    }
}
