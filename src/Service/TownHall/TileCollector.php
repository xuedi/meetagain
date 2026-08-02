<?php declare(strict_types=1);

namespace App\Service\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class TileCollector
{
    /** @param iterable<TileProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator(TileProviderInterface::class)]
        private iterable $providers,
    ) {}

    /** @return array<string, list<Tile>> */
    public function collectByLocation(): array
    {
        $tiles = [];
        foreach ($this->providers as $provider) {
            $tile = $provider->provide();
            if ($tile instanceof Tile) {
                $tiles[] = $tile;
            }
        }

        usort($tiles, static fn(Tile $left, Tile $right): int => $right->priority <=> $left->priority);

        $byLocation = [];
        foreach ($tiles as $tile) {
            $byLocation[$tile->location->value][] = $tile;
        }

        return $byLocation;
    }
}
