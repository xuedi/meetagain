<?php declare(strict_types=1);

namespace Plugin\Voting;

use App\Entity\Event;
use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventItemAssociationRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\ValueObject\LinkCollection;
use Plugin\Voting\Repository\PollRepository;
use Plugin\Voting\Service\PollService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PollService $pollService,
        private readonly PollRepository $pollRepository,
        private readonly EventRepository $eventRepository,
        private readonly EventItemAssociationRepository $associations,
        private readonly UserRepository $userRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'voting';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_voting_poll_list'), name: 'voting.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        if ($this->pollRepository->count([]) > 0) {
            $output->writeln('<comment>Voting: already seeded, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Voting: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();
        $voterIds = $this->voterIds(8);

        $seeded = [];
        foreach (['film', 'book', 'dish'] as $itemType) {
            $itemIds = $this->associations->findItemIdsByType($itemType);
            if (count($itemIds) < 2) {
                continue;
            }

            $candidates = array_slice($itemIds, 0, 5);
            $events = $this->eventsCarrying($itemType);
            if ($events === []) {
                continue;
            }

            $this->seedClosedPoll($events[0], $itemType, $candidates, $adminId, $voterIds);
            $this->seedOpenPoll($events[0], $itemType, $candidates, $adminId, $voterIds);

            $seeded[] = $itemType;
        }

        if ($seeded === []) {
            $output->writeln('<comment>Voting: no seeded items to vote on, skipping.</comment>');

            return;
        }

        $output->writeln(sprintf('<info>Voting: seeded polls over %s items.</info>', implode(', ', $seeded)));
    }

    /**
     * @param list<int> $candidates
     * @param list<int> $voterIds
     */
    private function seedClosedPoll(Event $event, string $itemType, array $candidates, int $adminId, array $voterIds): void
    {
        $poll = $this->pollService->create($event, $itemType, $candidates, 7, $adminId);
        foreach ($voterIds as $i => $voterId) {
            $this->pollService->castVote($voterId, $poll, [$candidates[$i % count($candidates)]]);
        }
        $closure = $this->pollService->close($poll);
        if ($closure->isTie()) {
            $this->pollService->commitOutcome($poll, $closure->tiedItemIds[0]);
        }
    }

    /**
     * @param list<int> $candidates
     * @param list<int> $voterIds
     */
    private function seedOpenPoll(Event $event, string $itemType, array $candidates, int $adminId, array $voterIds): void
    {
        $poll = $this->pollService->create($event, $itemType, $candidates, 7, $adminId);
        foreach (array_slice($voterIds, 0, 3) as $i => $voterId) {
            $this->pollService->castVote($voterId, $poll, [$candidates[$i % count($candidates)]]);
        }
    }

    /** @return list<Event> */
    private function eventsCarrying(string $itemType): array
    {
        $events = [];
        foreach ($this->associations->findEventIdsByType($itemType) as $eventId) {
            $event = $this->eventRepository->find($eventId);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /** @return list<int> */
    private function voterIds(int $limit): array
    {
        $ids = [];
        foreach ($this->userRepository->findAll() as $user) {
            $ids[] = (int) $user->getId();
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void {}

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void {}

    public function getStylesheets(): array
    {
        return ['styles/voting.css'];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
