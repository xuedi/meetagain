<?php declare(strict_types=1);

namespace Plugin\Voting;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventItemAssociationRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\ValueObject\LinkCollection;
use Plugin\Voting\Service\PollService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PollService $pollService,
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
        if ($this->pollService->getActivePolls() !== [] || $this->pollService->getClosedPolls() !== []) {
            $output->writeln('<comment>Voting: already seeded, skipping.</comment>');

            return;
        }

        // Voting is item-agnostic: seed over whichever item type has the most events-attached items,
        // read from the neutral core association table (no dependency on films/books/dishes).
        [$itemType, $itemIds] = $this->richestSeededItemType();
        if (count($itemIds) < 2) {
            $output->writeln('<comment>Voting: no seeded items to vote on, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Voting: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();
        $voterIds = $this->voterIds(8);

        $candidates = array_slice($itemIds, 0, 5);

        // Closed poll on a past event: cast a spread of votes, then close (steward tie-break if needed).
        $pastEvents = $this->eventRepository->getPastEvents(1);
        if ($pastEvents !== []) {
            $poll = $this->pollService->create($pastEvents[0], $itemType, $candidates, 7, $adminId);
            foreach ($voterIds as $i => $voterId) {
                $this->pollService->castVote($voterId, $poll, [$candidates[$i % count($candidates)]]);
            }
            $closure = $this->pollService->close($poll);
            if ($closure->isTie()) {
                $this->pollService->commitOutcome($poll, $closure->tiedItemIds[0]);
            }
        }

        // Open poll on the next upcoming event: a couple of early votes.
        $nextEventId = $this->eventRepository->getNextEventId();
        $nextEvent = $nextEventId !== null ? $this->eventRepository->find($nextEventId) : null;
        if ($nextEvent !== null) {
            $poll = $this->pollService->create($nextEvent, $itemType, $candidates, 7, $adminId);
            foreach (array_slice($voterIds, 0, 3) as $i => $voterId) {
                $this->pollService->castVote($voterId, $poll, [$candidates[$i % count($candidates)]]);
            }
        }

        $output->writeln(sprintf('<info>Voting: seeded polls over %s items.</info>', $itemType));
    }

    /**
     * @return array{0: string, 1: list<int>} the item type with the most events-attached items, and its ids
     */
    private function richestSeededItemType(): array
    {
        $best = ['film', []];
        foreach (['film', 'book', 'dish'] as $itemType) {
            $ids = $this->associations->findItemIdsByType($itemType);
            if (count($ids) > count($best[1])) {
                $best = [$itemType, $ids];
            }
        }

        return $best;
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
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
