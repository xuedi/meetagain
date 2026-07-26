<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the locale URLs emitted per event in the sitemap; unlisted events keep every
 * enabled locale. Returned locales outside the enabled set are ignored - this seam can
 * only narrow.
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
