<?php declare(strict_types=1);

namespace Plugin\Photos;

use App\Entity\Event;
use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\ValueObject\LinkCollection;
use Plugin\Photos\Event\SummaryTileBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventRepository $eventRepository,
        private readonly SummaryTileBuilder $summaryTileBuilder,
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
