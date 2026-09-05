<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\PledgeStatus;
use Symfony\Bundle\SecurityBundle\Security;

readonly class TileService
{
    public function __construct(
        private EventRepository $eventRepository,
        private PledgeService $pledges,
        private BringRequestService $requests,
        private ShelfService $shelf,
        private HeadcountService $headcount,
        private FitCalculator $fit,
        private Security $security,
    ) {}

    /**
     * @return array<string, mixed>|null null when the tile has nothing to say for this visitor
     */
    public function buildCenterTile(int $eventId): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $event = $this->eventRepository->find($eventId);
        if (!$event instanceof Event) {
            return null;
        }

        $headcount = $this->headcount->forEvent($event);
        $pledges = $this->pledges->getForEvent($eventId);

        return [
            'event' => $event,
            'headcount' => $headcount,
            'groups' => $this->groupByBringer($pledges, $headcount),
            'hasRsvp' => $event->hasRsvp($user),
            'bringable' => $this->bringableChoices($event, $user),
            'openRequests' => $this->requests->getOpenForEvent($eventId),
            'myOpenRequests' => $this->ownRequests($eventId, $user),
            'user' => $user,
        ];
    }

    /**
     * @param list<GamePledge> $pledges
     *
     * @return list<array{user: User, pledges: list<array{pledge: GamePledge, fit: string, fitClass: string, ownership: GameOwnership|null}>}>
     */
    private function groupByBringer(array $pledges, int $headcount): array
    {
        $grouped = [];
        foreach ($pledges as $pledge) {
            $bringer = $pledge->getUser();
            $game = $pledge->getGame();
            if ($bringer === null || $game === null) {
                continue;
            }

            $userId = (int) $bringer->getId();
            $fit = $this->fit->forGame($game, $headcount);
            $grouped[$userId]['user'] = $bringer;
            $grouped[$userId]['pledges'][] = [
                'pledge' => $pledge,
                'fit' => $fit->label(),
                'fitClass' => $fit->cssClass(),
                'ownership' => $this->shelf->getOwnership($bringer, $game),
            ];
        }

        return array_values($grouped);
    }

    /** @return list<GameOwnership> */
    private function bringableChoices(Event $event, User $user): array
    {
        if (!$event->hasRsvp($user)) {
            return [];
        }

        return array_values(array_filter(
            $this->shelf->getBringableShelf($user),
            fn(GameOwnership $ownership): bool => $ownership->getGame() !== null
                && $this->pledges->findOwn($event, $ownership->getGame(), $user)?->getStatus() !== PledgeStatus::Pledged,
        ));
    }

    /**
     * @return list<BringRequest>
     */
    private function ownRequests(int $eventId, User $user): array
    {
        return array_values(array_filter(
            $this->requests->getOpenForEvent($eventId),
            static fn(BringRequest $request): bool => $request->getOwnerUser()?->getId() === $user->getId(),
        ));
    }
}
