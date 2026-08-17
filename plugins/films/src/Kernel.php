<?php declare(strict_types=1);

namespace Plugin\Films;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\ValueObject\LinkCollection;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Repository\SettingsRepository;
use Plugin\Films\Service\FilmService;
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
        private readonly SettingsRepository $settingsRepository,
        private readonly SettingsService $settingsService,
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
            ['The Grand Budapest Hotel', 2014, 99, 'A concierge and his protege become embroiled in the theft of a priceless painting.', ['comedy', 'drama']],
            ['Parasite', 2019, 132, 'A poor family schemes to become employed by a wealthy household.', ['drama', 'thriller']],
            ['Spirited Away', 2001, 125, 'A girl wanders into a world ruled by gods and witches.', ['animation', 'fantasy']],
            ['Blade Runner 2049', 2017, 164, 'A young blade runner uncovers a secret that could plunge society into chaos.', ['scifi', 'drama']],
            ['Amelie', 2001, 122, 'A shy Parisian waitress decides to change the lives of those around her.', ['comedy', 'romance']],
            ['Whiplash', 2014, 106, 'A promising drummer enrolls at a cut-throat music conservatory.', ['drama']],
            ['Arrival', 2016, 116, 'A linguist works to communicate with extraterrestrial visitors.', ['scifi', 'mystery']],
            ['The Lives of Others', 2006, 137, 'A Stasi officer surveils a playwright in 1980s East Berlin.', ['drama', 'thriller']],
            ['In the Mood for Love', 2000, 98, 'Two neighbours form a bond after suspecting their spouses of infidelity.', ['drama', 'romance']],
            ['Portrait of a Lady on Fire', 2019, 122, 'A painter is commissioned to paint a reluctant bride-to-be.', ['drama', 'romance']],
        ];

        $created = [];
        foreach ($catalog as [$title, $year, $runtime, $description, $genres]) {
            $created[] = $this->filmService->createManual($title, $year, $runtime, $description, $genres, $adminId);
        }

        $events = $this->eventRepository->getPastEvents(6);
        if ($events === []) {
            $events = $this->eventRepository->getUpcomingEvents(6);
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
