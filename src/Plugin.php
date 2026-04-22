<?php declare(strict_types=1);

namespace App;

use App\Entity\EventListItemTag;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\ValueObject\LinkCollection;
use Symfony\Component\Console\Output\OutputInterface;
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
     * Runs fixture data creation after events have been extended.
     * Called by app:event:add-fixture command.
     */
    public function loadPostExtendFixtures(OutputInterface $output): void;

    /**
     * Runs pre-fixture tasks before plugin fixtures are loaded.
     * Called by app:plugin:pre-fixtures command, after base fixtures load.
     * Use this for migration tasks that need to run before plugin fixtures.
     */
    public function preFixtures(OutputInterface $output): void;

    /**
     * Runs post-fixture tasks after doctrine:fixtures:load completes.
     * Called by app:plugin:post-fixtures command.
     */
    public function postFixtures(OutputInterface $output): void;

    /**
     * Returns rendered HTML for the footer "about" section (copyright/branding area).
     * Return null if the plugin doesn't provide custom footer content.
     */
    public function getFooterAbout(): ?string;

    /**
     * Returns tags/badges to display for an event in list views.
     * @return list<EventListItemTag>
     */
    public function getEventListItemTags(int $eventId): array;

    /**
     * Pre-warms any per-request caches before a list render loop.
     * Called once with all visible IDs of the given type to avoid N individual queries.
     *
     * @param array<int> $ids
     */
    public function warmCache(WarmCacheType $type, array $ids): void;

    /**
     * Returns logical asset paths for stylesheets this plugin contributes.
     * Paths are relative to the plugin's assets/ directory.
     * Example: ['styles/filmclub.css']
     * @return list<string>
     */
    public function getStylesheets(): array;

    /**
     * Returns logical asset paths for JavaScript files this plugin contributes.
     * Paths are relative to the plugin's assets/ directory.
     * Example: ['js/ratings.js']
     * @return list<string>
     */
    public function getJavascripts(): array;

    /**
     * Returns this plugin's OpenAPI 3.1 fragment - the slice of paths, components, and tags
     * the plugin contributes to the public spec at /api/openapi.json. Return `[]` if the
     * plugin does not expose any API endpoints.
     *
     * Shape: ['paths' => [...], 'components' => ['schemas' => [...]], 'tags' => [...]]
     *
     * @return array<string, mixed>
     */
    public function getOpenApiFragment(): array;

}
