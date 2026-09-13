<?php declare(strict_types=1);

namespace Plugin\Wishlist;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Item\TypeRegistry;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TypeRegistry $typeRegistry,
    ) {}

    public function getPluginKey(): string
    {
        return 'wishlist';
    }

    public function getLinkCollection(): LinkCollection
    {
        if ($this->typeRegistry->all() === []) {
            return LinkCollection::empty();
        }

        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_wishlist_mine'), name: 'wishlist.menu_main'),
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
