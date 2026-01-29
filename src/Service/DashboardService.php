<?php declare(strict_types=1);

namespace App\Service;

use App\Dashboard\DashboardCenterTileInterface;
use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class DashboardService
{
    public function __construct(
        #[AutowireIterator(DashboardCenterTileInterface::class)]
        private iterable $centerTiles,
        #[AutowireIterator(DashboardSideTileInterface::class)]
        private iterable $sideTiles,
    ) {}

    /** Get center tiles accessible to user, sorted by priority */
    public function getCenterTilesForUser(User $user, ?object $group): array
    {
        return $this->filterAndSort($this->centerTiles, $user, $group);
    }

    /** Get side tiles accessible to user, sorted by priority */
    public function getSideTilesForUser(User $user, ?object $group): array
    {
        return $this->filterAndSort($this->sideTiles, $user, $group);
    }

    /**
     * @param iterable<DashboardCenterTileInterface|DashboardSideTileInterface> $tiles
     * @return array<DashboardCenterTileInterface|DashboardSideTileInterface>
     */
    private function filterAndSort(iterable $tiles, User $user, ?object $group): array
    {
        $accessible = [];
        foreach ($tiles as $tile) {
            if ($tile->isAccessible($user, $group)) {
                $accessible[] = $tile;
            }
        }
        usort($accessible, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        return $accessible;
    }
}
