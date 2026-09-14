<?php declare(strict_types=1);

namespace App;

use App\Entity\EventListItemTag;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\ValueObject\LinkCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Plugin
{
    /**
     * Returns a unique identifier for the plugin.
     */
    public function getPluginKey(): string;

    /**
     * Returns all link slots contributed by this plugin.
     */
    public function getLinkCollection(): LinkCollection;

    /**
     * Returns a rendered tile for the given location on the event details page.
     * Return null to contribute nothing for that location.
     */
    public function getEventTile(int $eventId, EventTileLocation $location): ?string;

    /**
     * Null contributes nothing to the footer "about" section.
     */
    public function getFooterAbout(): ?string;

    /**
     * @return list<EventListItemTag>
     */
    public function getEventListItemTags(int $eventId): array;

    /**
     * Called once with every visible id of the type, so implementations can avoid N queries.
     *
     * @param array<int> $ids
     */
    public function warmCache(WarmCacheType $type, array $ids): void;

    /**
     * Stylesheet paths relative to the plugin's assets/ directory, e.g. ['styles/main.css'].
     *
     * @return list<string>
     */
    public function getStylesheets(): array;

    /**
     * JavaScript paths relative to the plugin's assets/ directory, e.g. ['js/ratings.js'].
     *
     * @return list<string>
     */
    public function getJavascripts(): array;
}
