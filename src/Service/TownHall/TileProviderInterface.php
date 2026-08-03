<?php declare(strict_types=1);

namespace App\Service\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface TileProviderInterface
{
    /**
     * Contribute one tile to the hub page, or null to contribute nothing for this request.
     * Higher priority renders earlier within a location. Unrelated to App\Admin\Dashboard\Tile.
     */
    public function provide(): ?Tile;
}
