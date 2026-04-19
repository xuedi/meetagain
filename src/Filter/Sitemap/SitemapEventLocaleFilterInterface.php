<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Hook for restricting which locale URLs are emitted per event in the sitemap.
 *
 * The default core behavior is to emit every enabled locale for every event.
 * Plugins may implement this interface to limit specific events to a subset of
 * locales, preventing sitemap entries that would result in 404 responses.
 *
 * Return null to signal no restriction (all locales pass through).
 * Return a map of eventId => string[] to restrict specific events.
 * Events absent from the map are not restricted.
 */
#[AutoconfigureTag]
interface SitemapEventLocaleFilterInterface
{
    /**
     * @param int[] $eventIds
     * @return array<int, string[]>|null eventId => allowed locale codes, or null for no restriction
     */
    public function getAllowedLocalesByEventId(array $eventIds): ?array;
}
