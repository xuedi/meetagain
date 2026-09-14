<?php declare(strict_types=1);

namespace Plugin\Karaoke;

use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty();
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
