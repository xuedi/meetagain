<?php declare(strict_types=1);

namespace Plugin\Dinnerclub;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\EventType;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use App\Repository\EventRepository;
use Plugin\Dinnerclub\Repository\DinnerRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly DinnerRepository $dinnerRepository,
        private readonly EventRepository $eventRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'dinnerclub';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_plugin_dinnerclub'), name: 'dinnerclub.menu_dishes'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        if ($location !== EventTileLocation::Sidebar) {
            return null;
        }

        $event = $this->eventRepository->find($eventId);
        if ($event === null || $event->getType() !== EventType::Dinner) {
            return null;
        }

        $dinner = $this->dinnerRepository->findByEventId($eventId);

        return $this->twig->render('@Dinnerclub/tile/event.html.twig', [
            'dinner' => $dinner,
            'eventId' => $eventId,
        ]);
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }

    public function preFixtures(OutputInterface $output): void
    {
        // No pre-fixture tasks for this plugin
    }

    public function postFixtures(OutputInterface $output): void
    {
        // No post-fixture tasks for this plugin
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void
    {
    }


    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }

    public function getOpenApiFragment(): array
    {
        return [];
    }
}
