<?php declare(strict_types=1);

namespace App\Publisher\SiteName;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies a place that page titles add after the site name; null defers to the next provider, then no place.
 */
#[AutoconfigureTag]
interface TitlePlaceProviderInterface
{
    public function getTitlePlace(string $locale): ?string;
}
