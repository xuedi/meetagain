<?php declare(strict_types=1);

namespace App\Item\Location;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Suggests the city a new location starts with. Provider chain: the first non-null city id wins, and with no answer
 * the field starts empty.
 */
#[AutoconfigureTag]
interface CityDefaultProviderInterface
{
    public function getDefaultCityId(): ?int;
}
