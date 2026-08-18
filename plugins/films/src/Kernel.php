<?php declare(strict_types=1);

namespace Plugin\Films;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\Service\Item\SeedEventScope;
use App\ValueObject\LinkCollection;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Repository\SettingsRepository;
use Plugin\Films\Service\FilmService;
use Plugin\Films\Service\FixturePosterService;
use Plugin\Films\Service\SettingsService;
use SensitiveParameter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FilmService $filmService,
        private readonly AssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
        private readonly SeedEventScope $seedEventScope,
        private readonly SettingsRepository $settingsRepository,
        private readonly SettingsService $settingsService,
        private readonly FixturePosterService $fixturePosterService,
        #[Autowire('%env(default::TMDB_API_KEY)%')]
        #[SensitiveParameter]
        private readonly ?string $tmdbApiKey,
        #[Autowire('%env(default::OMDB_API_KEY)%')]
        #[SensitiveParameter]
        private readonly ?string $omdbApiKey,
    ) {}

    public function getPluginKey(): string
    {
        return 'films';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_films_filmlist'), name: 'films.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        if ($this->filmService->getList() !== []) {
            $output->writeln('<comment>Films: already seeded, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Films: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();

        $catalog = [
            ['The Grand Budapest Hotel', 2014, 99, 'A concierge and his protege become embroiled in the theft of a priceless painting.', ['comedy', 'drama'], 'the-grand-budapest-hotel-2014.jpg'],
            ['Parasite', 2019, 132, 'A poor family schemes to become employed by a wealthy household.', ['drama', 'thriller'], 'parasite-2019.jpg'],
            ['Spirited Away', 2001, 125, 'A girl wanders into a world ruled by gods and witches.', ['animation', 'fantasy'], 'spirited-away-2001.jpg'],
            ['Blade Runner 2049', 2017, 164, 'A young blade runner uncovers a secret that could plunge society into chaos.', ['scifi', 'drama'], 'blade-runner-2049-2017.jpg'],
            ['Amelie', 2001, 122, 'A shy Parisian waitress decides to change the lives of those around her.', ['comedy', 'romance'], 'amelie-2001.jpg'],
            ['Whiplash', 2014, 106, 'A promising drummer enrolls at a cut-throat music conservatory.', ['drama'], 'whiplash-2014.jpg'],
            ['Arrival', 2016, 116, 'A linguist works to communicate with extraterrestrial visitors.', ['scifi', 'mystery'], 'arrival-2016.jpg'],
            ['The Lives of Others', 2006, 137, 'A Stasi officer surveils a playwright in 1980s East Berlin.', ['drama', 'thriller'], 'the-lives-of-others-2006.jpg'],
            ['In the Mood for Love', 2000, 98, 'Two neighbours form a bond after suspecting their spouses of infidelity.', ['drama', 'romance'], 'in-the-mood-for-love-2000.jpg'],
            ['Portrait of a Lady on Fire', 2019, 122, 'A painter is commissioned to paint a reluctant bride-to-be.', ['drama', 'romance'], 'portrait-of-a-lady-on-fire-2019.jpg'],
            ['Everything Everywhere All at Once', 2022, 139, 'A laundromat owner audited by the IRS discovers she can borrow the skills of her other selves.', ['scifi', 'comedy'], 'everything-everywhere-all-at-once-2022.jpg'],
            ['Mad Max: Fury Road', 2015, 120, 'A war rig breaks for the desert with five escaped wives and half a warlord army behind it.', ['action', 'scifi'], 'mad-max-fury-road-2015.jpg'],
            ['Get Out', 2017, 104, 'A weekend with his girlfriend\'s parents turns very slowly into something a guest cannot leave.', ['horror', 'thriller'], 'get-out-2017.jpg'],
            ['La La Land', 2016, 128, 'A jazz pianist and an aspiring actress fall in love while chasing careers that pull them apart.', ['drama', 'musical'], 'la-la-land-2016.jpg'],
            ['Dune', 2021, 155, 'A noble house takes over a desert planet whose only export is worth more than the house itself.', ['scifi', 'adventure'], 'dune-2021.jpg'],
            ['Knives Out', 2019, 130, 'A famous novelist dies after his birthday party and every relative had a reason to want it.', ['mystery', 'comedy'], 'knives-out-2019.jpg'],
            ['The Social Network', 2010, 120, 'A Harvard student builds a website that costs him nearly everyone who helped him build it.', ['drama'], 'the-social-network-2010.jpg'],
            ['Moonlight', 2016, 111, 'Three chapters in the life of a boy growing up in Miami and learning what he is allowed to want.', ['drama'], 'moonlight-2016.jpg'],
            ['Her', 2013, 126, 'A lonely letter writer falls in love with the operating system he bought to organise his life.', ['scifi', 'romance'], 'her-2013.jpg'],
            ['Interstellar', 2014, 169, 'A pilot leaves his children behind to look for a habitable world on the far side of a wormhole.', ['scifi', 'drama'], 'interstellar-2014.jpg'],
        ];

        $created = [];
        foreach ($catalog as [$title, $year, $runtime, $description, $genres, $poster]) {
            $film = $this->filmService->createManual($title, $year, $runtime, $description, $genres, $adminId);
            $this->fixturePosterService->attach($film, $poster, $admin);
            $created[] = $film;
        }

        $events = $this->seedEventScope->filter($this->eventRepository->getPastEvents(6), $this->getPluginKey());
        if ($events === []) {
            $events = $this->seedEventScope->filter($this->eventRepository->getUpcomingEvents(6), $this->getPluginKey());
        }
        $attached = 0;
        foreach ($created as $index => $film) {
            if ($events === [] || $attached >= 6) {
                break;
            }
            $event = $events[$index % count($events)];
            $this->itemAssociations->attach((int) $event->getId(), FilmService::ITEM_TYPE, (int) $film->getId(), $adminId, $index);
            $attached++;
        }

        $output->writeln(sprintf('<info>Films: seeded %d films, attached %d to events.</info>', count($created), $attached));
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void
    {
        $tmdbKey = $this->tmdbApiKey ?: null;
        $omdbKey = $this->omdbApiKey ?: null;
        if ($tmdbKey === null && $omdbKey === null) {
            return;
        }

        $settings = $this->settingsRepository->findGlobal() ?? new Settings();
        if ($tmdbKey !== null) {
            $settings->setEncryptedTmdbKey($this->settingsService->encryptKey($tmdbKey));
            $settings->setAdapter(ExternalSource::Tmdb);
        }
        if ($omdbKey !== null) {
            $settings->setEncryptedOmdbKey($this->settingsService->encryptKey($omdbKey));
            if ($settings->getAdapter() === null) {
                $settings->setAdapter(ExternalSource::Omdb);
            }
        }
        $this->settingsService->save($settings);

        $output->writeln('<info>Films: seeded metadata lookup settings from environment keys.</info>');
    }

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
