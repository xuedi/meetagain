<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\PledgeStatus;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Repository\GamePledgeRepository;
use RuntimeException;

readonly class PledgeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private GamePledgeRepository $pledgeRepo,
        private GameOwnershipRepository $ownershipRepo,
    ) {}

    /** @return list<GamePledge> */
    public function getForEvent(int $eventId): array
    {
        return $this->pledgeRepo->findActiveForEvent($eventId);
    }

    public function countForEvent(int $eventId): int
    {
        return $this->pledgeRepo->countActiveForEvent($eventId);
    }

    public function pledge(Event $event, Game $game, User $user): GamePledge
    {
        if (!$event->hasRsvp($user)) {
            throw new RuntimeException('boardgames_tile.flash_rsvp_required');
        }

        if ($this->ownershipRepo->findOneFor($user, $game) === null) {
            throw new RuntimeException('boardgames_tile.flash_ownership_required');
        }

        $pledge = $this->pledgeRepo->findOneFor($event, $game, $user);
        if ($pledge === null) {
            $pledge = new GamePledge();
            $pledge->setEvent($event);
            $pledge->setGame($game);
            $pledge->setUser($user);
            $pledge->setCreatedAt(new DateTimeImmutable());
        }

        $pledge->setStatus(PledgeStatus::Pledged);

        $this->em->persist($pledge);
        $this->em->flush();

        return $pledge;
    }

    public function withdraw(GamePledge $pledge): void
    {
        $pledge->setStatus(PledgeStatus::Withdrawn);
        $this->em->persist($pledge);
        $this->em->flush();
    }

    public function findOwn(Event $event, Game $game, User $user): ?GamePledge
    {
        return $this->pledgeRepo->findOneFor($event, $game, $user);
    }

    public function findOwnedBy(int $pledgeId, User $user): ?GamePledge
    {
        $pledge = $this->pledgeRepo->find($pledgeId);

        return $pledge?->getUser()?->getId() === $user->getId() ? $pledge : null;
    }
}
