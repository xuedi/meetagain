<?php declare(strict_types=1);

namespace Plugin\Boardgames;

use App\Entity\Event;
use App\Entity\EventListItemTag;
use App\Entity\Link;
use App\Entity\User;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Item\Tag\TagService;
use App\Plugin;
use App\Publisher\PluginSettings\Resolver;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\Service\Item\SeedEventScope;
use App\Service\Security\SecretBox;
use App\ValueObject\LinkCollection;
use DateTimeImmutable;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\CopyCondition;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Repository\GameRepository;
use Plugin\Boardgames\Service\FixtureBoxService;
use Plugin\Boardgames\Service\FixtureCatalogService;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\PledgeService;
use Plugin\Boardgames\Service\ShelfService;
use Plugin\Boardgames\Service\TileService;
use Plugin\Boardgames\ValueObject\Config;
use SensitiveParameter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly GameService $gameService,
        private readonly ShelfService $shelfService,
        private readonly PledgeService $pledgeService,
        private readonly TileService $tileService,
        private readonly TagService $tagService,
        private readonly AssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
        private readonly SeedEventScope $seedEventScope,
        private readonly FixtureBoxService $fixtureBoxService,
        private readonly FixtureCatalogService $fixtureCatalog,
        private readonly GameRepository $gameRepository,
        private readonly Resolver $settingsResolver,
        private readonly SecretBox $secretBox,
        #[Autowire('%env(default::BGG_API_TOKEN)%')]
        #[SensitiveParameter]
        private readonly ?string $bggApiToken,
    ) {}

    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_boardgames_gamelist'), name: 'boardgames.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        if ($location !== EventTileLocation::Center) {
            return null;
        }

        $tile = $this->tileService->buildCenterTile($eventId);
        if ($tile === null) {
            return null;
        }

        return $this->twig->render('@Boardgames/tile/center.html.twig', $tile);
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        if ($this->gameRepository->count([]) > 0) {
            $output->writeln('<comment>Boardgames: already seeded, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Boardgames: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();

        $tagIds = $this->seedVocabulary();

        $created = [];
        foreach ($this->fixtureCatalog->games() as $row) {
            $game = $this->gameService->createManual(
                $row['name'],
                $row['year'],
                $row['minPlayers'],
                $row['maxPlayers'],
                $row['minPlaytime'],
                $row['maxPlaytime'],
                $row['weight'],
                $row['description'],
                $adminId,
            );
            $this->fixtureBoxService->attach($game, $row['box'], $admin);
            $this->tagService->setTags(
                GameService::ITEM_TYPE,
                (int) $game->getId(),
                array_values(array_filter(array_map(static fn(string $tag): ?int => $tagIds[$tag] ?? null, $row['tags']))),
            );
            $created[] = $game;
        }

        $events = $this->seedEvents();
        $attached = $this->attachToEvents($created, $events, $adminId);
        $shelved = $this->seedShelves($created, $this->shelfMembers($events));
        $pledged = $this->seedPledges($created, $events);

        $output->writeln(sprintf(
            '<info>Boardgames: seeded %d games, attached %d to events, filled %d shelf rows, %d pledges.</info>',
            count($created),
            $attached,
            $shelved,
            $pledged,
        ));
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void
    {
        $token = $this->bggApiToken ?: null;
        if ($token === null) {
            return;
        }

        $store = $this->settingsResolver->resolveStore('boardgames', null);
        if ($store === null) {
            return;
        }

        $config = $store->load('boardgames', null);
        if (!$config instanceof Config) {
            $config = new Config();
        }
        $config->setEncryptedBggToken($this->secretBox->encrypt($token));
        $config->setAdapter(ExternalSource::Bgg);
        $store->save('boardgames', $config, null);

        $output->writeln('<info>Boardgames: seeded the BoardGameGeek token from the environment.</info>');
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        $count = $this->pledgeService->countForEvent($eventId);
        if ($count === 0) {
            return [];
        }

        return [new EventListItemTag(
            text: $this->translator->trans('boardgames.event_list_tag', ['%count%' => $count]),
            icon: 'fa fa-dice',
        )];
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

    /**
     * @param list<Game>  $games
     * @param list<Event> $events
     */
    private function seedPledges(array $games, array $events): int
    {
        if ($events === [] || $games === []) {
            return 0;
        }

        $event = $events[0];
        $pledged = 0;
        foreach ($this->shelfMembers([$event]) as $index => $member) {
            $game = $games[$index] ?? null;
            if ($game === null || !$event->hasRsvp($member)) {
                continue;
            }

            $this->shelfService->add($member, $game);
            $this->pledgeService->pledge($event, $game, $member);
            $pledged++;
        }

        return $pledged;
    }

    /** @return array<string, int> tag slug => tag id */
    private function seedVocabulary(): array
    {
        $tags = [];
        $ids = [];
        foreach ($this->fixtureCatalog->vocabulary() as $row) {
            $parent = $row['parent'] === null ? null : ($tags[$row['parent']] ?? null);
            $tag = $this->tagService->addTag(GameService::ITEM_TYPE, $row['labels'], $parent);
            $tags[$row['slug']] = $tag;
            $ids[$row['slug']] = (int) $tag->getId();
        }

        return $ids;
    }

    /** @return list<Event> */
    private function seedEvents(): array
    {
        $events = $this->seedEventScope->filter($this->eventRepository->getUpcomingEvents(4), $this->getPluginKey());
        if ($events === []) {
            $events = $this->seedEventScope->filter($this->eventRepository->getPastEvents(4), $this->getPluginKey());
        }

        return array_values($events);
    }

    /**
     * @param list<Game>  $games
     * @param list<Event> $events
     */
    private function attachToEvents(array $games, array $events, int $adminId): int
    {
        $attached = 0;
        foreach ($games as $index => $game) {
            if ($events === [] || $attached >= 6) {
                break;
            }
            $event = $events[$index % count($events)];
            $this->itemAssociations->attach((int) $event->getId(), GameService::ITEM_TYPE, (int) $game->getId(), $adminId, $index);
            $attached++;
        }

        return $attached;
    }

    /**
     * @param list<Event> $events
     *
     * @return list<User>
     */
    private function shelfMembers(array $events): array
    {
        $members = [];
        foreach ($events as $event) {
            foreach ($event->getRsvp() as $member) {
                $members[(int) $member->getId()] = $member;
                if (count($members) >= 3) {
                    return array_values($members);
                }
            }
        }

        return array_values($members);
    }

    /**
     * @param list<Game> $games
     * @param list<User> $members
     */
    private function seedShelves(array $games, array $members): int
    {
        if (count($members) < 2 || $games === []) {
            return 0;
        }

        $plans = [
            [0, [0, 1, 2, 3, 4], true],
            [1, [3, 4, 5, 6, 7], false],
        ];

        $rows = 0;
        foreach ($plans as [$memberIndex, $gameIndexes, $canTeach]) {
            if (!isset($members[$memberIndex])) {
                continue;
            }

            foreach ($gameIndexes as $gameIndex) {
                if (!isset($games[$gameIndex])) {
                    continue;
                }

                $ownership = $this->shelfService->add($members[$memberIndex], $games[$gameIndex]);
                $ownership->setCanTeach($canTeach && $gameIndex % 2 === 0);
                $ownership->setPublic($gameIndex !== 6);
                $ownership->setWillingToBring($gameIndex !== 5);
                $ownership->setCopyLanguage($gameIndex % 2 === 0 ? 'en' : 'de');
                $ownership->setCopyCondition(CopyCondition::Good);
                $ownership->setAcquiredAt(new DateTimeImmutable('-1 year'));
                $this->shelfService->save($ownership);
                $rows++;
            }
        }

        return $rows;
    }
}
