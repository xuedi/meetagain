<?php declare(strict_types=1);

namespace Plugin\Boardgames;

use App\Entity\EventListItemTag;
use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Plugin\Boardgames\Service\PledgeService;
use Plugin\Boardgames\Service\TileService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly PledgeService $pledgeService,
        private readonly TileService $tileService,
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

        return [new EventListItemTag(text: $this->translator->trans('boardgames.event_list_tag', ['%count%' => $count]), icon: 'fa fa-dice')];
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
