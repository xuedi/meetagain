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
    public function getCenterTilesForUser(User $user): array
    {
        return $this->filterAndSort($this->centerTiles, $user);
    }

    /** Get side tiles accessible to user, sorted by priority */
    public function getSideTilesForUser(User $user): array
    {
        return $this->filterAndSort($this->sideTiles, $user);
    }

    /**
     * @param iterable<DashboardCenterTileInterface|DashboardSideTileInterface> $tiles
     * @return array<DashboardCenterTileInterface|DashboardSideTileInterface>
     */
    private function filterAndSort(iterable $tiles, User $user): array
    {
        $accessible = [];
        foreach ($tiles as $tile) {
            if ($tile->isAccessible($user)) {
                $accessible[] = $tile;
            }
        }
        usort($accessible, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        return $accessible;
    }
}
