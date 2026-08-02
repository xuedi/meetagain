<?php declare(strict_types=1);

namespace App\Service\TownHall;

use App\Enum\TownHallTileLocation;

final readonly class Tile
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $partial,
        public array $context,
        public TownHallTileLocation $location,
        public int $priority = 0,
    ) {}
}
