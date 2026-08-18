<?php declare(strict_types=1);

namespace Plugin\Photos;

use App\Entity\EventItemAssociation;
use App\Entity\Link;
use App\Entity\User;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\AssociationService;
use App\Service\Item\SeedEventScope;
use App\ValueObject\LinkCollection;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    private const string FIXTURE_DIR = __DIR__ . '/../fixtures';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PhotoService $photoService,
        private readonly AssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
        private readonly SeedEventScope $seedEventScope,
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
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        $pastEvents = $this->seedEventScope->filter($this->eventRepository->getPastEvents(1), $this->getPluginKey());
        if ($pastEvents === []) {
            $output->writeln('<comment>Photos: no past event to attach to, skipping.</comment>');

            return;
        }

        $eventId = (int) $pastEvents[0]->getId();
        $attachedAlready = array_any(
            $this->itemAssociations->listForEvent($eventId),
            static fn(EventItemAssociation $association): bool => $association->getItemType() === PhotoService::ITEM_TYPE,
        );
        if ($attachedAlready) {
            $output->writeln('<comment>Photos: already seeded, skipping.</comment>');

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
            ++$attached;
        }

        $output->writeln(sprintf('<info>Photos: seeded and attached %d photos to a past event.</info>', $attached));
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
            ['night-market.jpg', [
                'en' => ['title' => 'Night market', 'description' => 'Handheld at 1/60 - the light was almost gone.'],
            ]],
            ['kitchen-table.jpg', [
                'en' => ['title' => 'Kitchen table still life', 'description' => 'Everything that was left after the tasting evening.'],
                'de' => ['title' => 'Stillleben auf dem Küchentisch', 'description' => 'Alles, was nach dem Verkostungsabend übrig blieb.'],
            ]],
            ['rooftop-portrait.jpg', []],
        ];
    }
}
