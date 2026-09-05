<?php declare(strict_types=1);

namespace Plugin\Photos;

use App\Entity\Event;
use App\Entity\EventItemAssociation;
use App\Entity\Link;
use App\Entity\User;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Event\EventScope;
use App\Service\Item\AssociationService;
use App\Service\Item\SeedEventScope;
use App\ValueObject\LinkCollection;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Event\DateTagService;
use Plugin\Photos\Event\SummaryTileBuilder;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    private const string FIXTURE_DIR = __DIR__ . '/../fixtures';

    private const int EVENT_SEARCH_DEPTH = 30;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PhotoService $photoService,
        private readonly AssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
        private readonly SeedEventScope $seedEventScope,
        private readonly EventScope $eventScope,
        private readonly DateTagService $dateTagService,
        private readonly SummaryTileBuilder $summaryTileBuilder,
        private readonly ContestService $contestService,
        private readonly Environment $twig,
    ) {}

    public function getPluginKey(): string
    {
        return 'photos';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_photos_photolist'), name: 'photos.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        $event = $location === EventTileLocation::Sidebar ? $this->eventRepository->find($eventId) : null;
        $tile = $event instanceof Event ? $this->summaryTileBuilder->build($event) : null;

        return $tile === null ? null : $this->twig->render('@Photos/event/summary_tile.html.twig', $tile);
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        $pastEvents = $this->seedEventScope->filter($this->eventRepository->getPastEvents(self::EVENT_SEARCH_DEPTH), $this->getPluginKey());
        if ($pastEvents === []) {
            $output->writeln('<comment>Photos: no past event to attach to, skipping.</comment>');

            return;
        }

        $event = $pastEvents[0];
        $eventId = (int) $event->getId();
        $attachedAlready = array_any(
            $this->itemAssociations->listForEvent($eventId),
            static fn(EventItemAssociation $association): bool => $association->getItemType() === PhotoService::ITEM_TYPE,
        );
        if ($attachedAlready) {
            $output->writeln('<comment>Photos: already seeded, skipping.</comment>');
            $this->seedContests($output, $pastEvents);

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if (!$admin instanceof User) {
            $output->writeln('<comment>Photos: no admin user found, skipping.</comment>');

            return;
        }

        $attached = 0;
        foreach ($this->catalog() as [$file, $translations]) {
            $path = self::FIXTURE_DIR . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            $photo = $this->photoService->create(new UploadedFile($path, $file, null, null, true), $admin, $translations);
            if ($photo === null) {
                continue;
            }

            $this->itemAssociations->attach($eventId, PhotoService::ITEM_TYPE, (int) $photo->getId(), (int) $admin->getId(), $attached);
            $this->eventScope->runForEvent($eventId, fn(): null => $this->dateTagService->assign($event, (int) $photo->getId()));
            ++$attached;
        }

        $output->writeln(sprintf('<info>Photos: seeded and attached %d photos to a past event.</info>', $attached));
        $this->seedContests($output, $pastEvents);
    }

    /** @param list<Event> $candidates each one only names a scope to look for a collection in */
    private function seedContests(OutputInterface $output, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $eventId = (int) $candidate->getId();
            $seeded = $this->eventScope->runForEvent(
                $eventId,
                fn(): bool => $this->contestService->isSeedable() && $this->contestService->seedDemo($this->scopedPhotoIds()),
            );

            if ($seeded) {
                $output->writeln('<info>Photos: seeded a finished contest, an open one and a queue.</info>');

                return;
            }
        }

        $output->writeln('<comment>Photos: no group with enough photos for a contest demo, skipping.</comment>');
    }

    /** @return list<int> a contest is over the whole collection, not over one event's photos */
    private function scopedPhotoIds(): array
    {
        return array_map(static fn(Photo $photo): int => (int) $photo->getId(), $this->photoService->getList());
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

    /**
     * @return list<array{0: string, 1: array<string, array<string, string>>}>
     */
    private function catalog(): array
    {
        return [
            [
                'night-market.jpg',
                [
                    'en' => ['title' => 'Night market', 'description' => 'Handheld at 1/60 - the light was almost gone.'],
                ],
            ],
            [
                'kitchen-table.jpg',
                [
                    'en' => ['title' => 'Kitchen table still life', 'description' => 'Everything that was left after the tasting evening.'],
                    'de' => ['title' => 'Stillleben auf dem Küchentisch', 'description' => 'Alles, was nach dem Verkostungsabend übrig blieb.'],
                ],
            ],
            ['rooftop-portrait.jpg', []],
        ];
    }
}
