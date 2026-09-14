<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_dishes_dishlist'), name: 'dishes.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
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
