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
use Plugin\Boardgames\Service\FixtureBoxService;
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
        if ($this->gameService->getList() !== []) {
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
        foreach ($this->catalog() as [$name, $year, $minPlayers, $maxPlayers, $minTime, $maxTime, $weight, $description, $tags, $box]) {
            $game = $this->gameService->createManual($name, $year, $minPlayers, $maxPlayers, $minTime, $maxTime, $weight, $description, $adminId);
            $this->fixtureBoxService->attach($game, $box, $admin);
            $this->tagService->setTags(
                GameService::ITEM_TYPE,
                (int) $game->getId(),
                array_values(array_filter(array_map(static fn(string $tag): ?int => $tagIds[$tag] ?? null, $tags))),
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
        $mechanics = $this->tagService->addTag(GameService::ITEM_TYPE, $this->labels('Mechanics', 'Mechaniken', '机制', 'Mecaniques', 'Mecanicas'), null);
        $categories = $this->tagService->addTag(GameService::ITEM_TYPE, $this->labels('Categories', 'Kategorien', '类别', 'Categories', 'Categorias'), null);

        $ids = [];
        foreach ([
            ['deck-building', $mechanics, ['Deck building', 'Deckbau', '构筑牌组', 'Construction de deck', 'Construccion de mazos']],
            ['worker-placement', $mechanics, ['Worker placement', 'Arbeitereinsatz', '工人放置', "Placement d'ouvriers", 'Colocacion de trabajadores']],
            ['tile-laying', $mechanics, ['Tile laying', 'Plättchenlegen', '版图拼放', 'Pose de tuiles', 'Colocacion de losetas']],
            ['area-control', $mechanics, ['Area control', 'Gebietskontrolle', '区域控制', 'Controle de zone', 'Control de area']],
            ['cooperative', $mechanics, ['Cooperative', 'Kooperativ', '合作', 'Cooperatif', 'Cooperativo']],
            ['trading', $mechanics, ['Trading', 'Handel', '交易', 'Commerce', 'Comercio']],
            ['hidden-roles', $mechanics, ['Hidden roles', 'Verdeckte Rollen', '隐藏身份', 'Roles caches', 'Roles ocultos']],
            ['party', $categories, ['Party game', 'Partyspiel', '聚会游戏', "Jeu d'ambiance", 'Juego de fiesta']],
            ['strategy', $categories, ['Strategy', 'Strategie', '策略', 'Strategie', 'Estrategia']],
            ['family', $categories, ['Family', 'Familie', '家庭', 'Famille', 'Familiar']],
            ['abstract', $categories, ['Abstract', 'Abstrakt', '抽象', 'Abstrait', 'Abstracto']],
            ['wordgame', $categories, ['Word game', 'Wortspiel', '文字游戏', 'Jeu de mots', 'Juego de palabras']],
        ] as [$slug, $parent, $labels]) {
            $tag = $this->tagService->addTag(GameService::ITEM_TYPE, $this->labels(...$labels), $parent);
            $ids[$slug] = (int) $tag->getId();
        }

        return $ids;
    }

    /** @return array<string, string> */
    private function labels(string $en, string $de, string $zh, string $fr, string $es): array
    {
        return ['en' => $en, 'de' => $de, 'zh' => $zh, 'fr' => $fr, 'es' => $es];
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

    /** @return list<array{0: string, 1: int, 2: int, 3: int, 4: int, 5: int, 6: string, 7: string, 8: list<string>, 9: string}> */
    private function catalog(): array
    {
        return [
            ['Catan', 1995, 3, 4, 60, 120, '2.30', 'Settlers trade and build on an island whose resources never quite line up.', ['trading', 'strategy'], 'catan-1995.jpg'],
            ['Carcassonne', 2000, 2, 5, 30, 45, '1.90', 'Players lay tiles to build a medieval landscape and claim its features.', ['tile-laying', 'family'], 'carcassonne-2000.jpg'],
            ['Ticket to Ride', 2004, 2, 5, 30, 60, '1.80', 'Collect train cards and claim railway routes across a continent.', ['family', 'strategy'], 'ticket-to-ride-2004.jpg'],
            ['Pandemic', 2008, 2, 4, 45, 45, '2.40', 'A team of specialists races four diseases around the world.', ['cooperative', 'strategy'], 'pandemic-2008.jpg'],
            ['7 Wonders', 2010, 3, 7, 30, 30, '2.30', 'Three ages of card drafting build a wonder of the ancient world.', ['strategy'], '7-wonders-2010.jpg'],
            ['Dominion', 2008, 2, 4, 30, 30, '2.40', 'Every player builds a deck from a shared supply of kingdom cards.', ['deck-building', 'strategy'], 'dominion-2008.jpg'],
            ['Azul', 2017, 2, 4, 30, 45, '1.80', 'Tile drafting for the walls of the Royal Palace of Evora.', ['abstract', 'family'], 'azul-2017.jpg'],
            ['Wingspan', 2019, 1, 5, 40, 70, '2.40', 'A bird collection engine builder set in forest, grassland and wetland.', ['strategy'], 'wingspan-2019.jpg'],
            ['Codenames', 2015, 2, 8, 15, 15, '1.30', 'Two spymasters give one-word clues to contact their agents first.', ['party', 'wordgame'], 'codenames-2015.jpg'],
            ['The Crew', 2019, 2, 5, 20, 20, '2.00', 'A cooperative trick-taking campaign to the ninth planet.', ['cooperative'], 'the-crew-2019.jpg'],
            ['Splendor', 2014, 2, 4, 30, 30, '1.80', 'Renaissance merchants collect gem tokens and buy development cards.', ['strategy', 'family'], 'splendor-2014.jpg'],
            ['Scythe', 2016, 1, 5, 90, 115, '3.40', 'Five factions compete for a war-torn 1920s Eastern Europe.', ['area-control', 'strategy'], 'scythe-2016.jpg'],
            ['Terraforming Mars', 2016, 1, 5, 120, 120, '3.20', 'Corporations raise the temperature, oxygen and oceans of Mars.', ['strategy'], 'terraforming-mars-2016.jpg'],
            ['Agricola', 2007, 1, 5, 30, 150, '3.60', 'A farming family expands its house, fields and pastures.', ['worker-placement', 'strategy'], 'agricola-2007.jpg'],
            ['Everdell', 2018, 1, 4, 40, 80, '2.80', 'Woodland critters build a city of cards through the four seasons.', ['worker-placement', 'strategy'], 'everdell-2018.jpg'],
            ['The Resistance: Avalon', 2012, 5, 10, 30, 30, '1.80', 'Loyal servants of Arthur hunt the minions of Mordred among them.', ['hidden-roles', 'party'], 'avalon-2012.jpg'],
            ['Just One', 2018, 3, 7, 20, 20, '1.10', 'Everyone writes a one-word clue, and matching clues cancel out.', ['party', 'wordgame'], 'just-one-2018.jpg'],
            ['Patchwork', 2014, 2, 2, 15, 30, '1.60', 'Two quilters buy fabric patches against a shared time track.', ['abstract'], 'patchwork-2014.jpg'],
            ['Hive', 2001, 2, 2, 20, 20, '2.30', 'Insect tiles surround the opposing queen bee on an open board.', ['abstract'], 'hive-2001.jpg'],
            ['Spirit Island', 2017, 1, 4, 90, 120, '4.00', 'Island spirits push back colonial invaders before the island is spoiled.', ['cooperative', 'strategy'], 'spirit-island-2017.jpg'],
        ];
    }
}
