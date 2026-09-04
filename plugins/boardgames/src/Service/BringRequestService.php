<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\RequestStatus;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use RuntimeException;

readonly class BringRequestService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BringRequestRepository $requestRepo,
        private GameOwnershipRepository $ownershipRepo,
        private PledgeService $pledges,
    ) {}

    public function ask(Event $event, Game $game, User $requester, User $owner, ?string $message): BringRequest
    {
        if ($requester->getId() === $owner->getId()) {
            throw new RuntimeException('boardgames_tile.flash_cannot_ask_self');
        }

        $ownership = $this->ownershipRepo->findOneFor($owner, $game);
        if ($ownership === null || !$ownership->isAskable()) {
            throw new RuntimeException('boardgames_tile.flash_owner_not_askable');
        }

        $existing = $this->requestRepo->findOneFor($event, $game, $requester);
        if ($existing !== null) {
            throw new RuntimeException(match ($existing->getStatus()) {
                RequestStatus::Open => 'boardgames_tile.flash_request_already_open',
                RequestStatus::Declined => 'boardgames_tile.flash_request_declined_before',
                default => 'boardgames_tile.flash_request_already_made',
            });
        }

        $request = new BringRequest();
        $request->setEvent($event);
        $request->setGame($game);
        $request->setRequestedBy($requester);
        $request->setOwnerUser($owner);
        $request->setMessage($message);
        $request->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    public function accept(BringRequest $request): void
    {
        $this->assertOpen($request);

        $event = $request->getEvent();
        $game = $request->getGame();
        $owner = $request->getOwnerUser();
        if ($event === null || $game === null || $owner === null) {
            throw new RuntimeException('boardgames_tile.flash_request_stale');
        }

        $this->pledges->pledge($event, $game, $owner);
        $this->close($request, RequestStatus::Accepted);
    }

    public function decline(BringRequest $request): void
    {
        $this->assertOpen($request);
        $this->close($request, RequestStatus::Declined);
    }

    public function withdraw(BringRequest $request): void
    {
        $this->assertOpen($request);
        $this->close($request, RequestStatus::Withdrawn);
    }

    public function expire(BringRequest $request): void
    {
        $this->close($request, RequestStatus::Expired);
    }

    /** @return list<BringRequest> */
    public function getOpenForEvent(int $eventId): array
    {
        return $this->requestRepo->findOpenForEvent($eventId);
    }

    /** @return list<BringRequest> */
    public function getOpenForOwner(User $owner): array
    {
        return $this->requestRepo->findOpenForOwner($owner);
    }

    public function find(int $id): ?BringRequest
    {
        return $this->requestRepo->find($id);
    }

    private function assertOpen(BringRequest $request): void
    {
        if ($request->getStatus() !== RequestStatus::Open) {
            throw new RuntimeException('boardgames_tile.flash_request_closed');
        }
    }

    private function close(BringRequest $request, RequestStatus $status): void
    {
        $request->setStatus($status);
        $request->setRespondedAt(new DateTimeImmutable());

        $this->em->persist($request);
        $this->em->flush();
    }
}
