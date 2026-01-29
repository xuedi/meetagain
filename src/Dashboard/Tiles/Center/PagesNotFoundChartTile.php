<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Center;

use App\Dashboard\DashboardCenterTileInterface;
use App\Entity\User;
use App\Service\DashboardStatsService;

readonly class PagesNotFoundChartTile implements DashboardCenterTileInterface
{
    public function __construct(
        private DashboardStatsService $statsService,
    ) {}

    public function getKey(): string
    {
        return 'pages_not_found_chart';
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function isAccessible(User $user, ?object $group): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function getData(User $user, ?object $group, int $year, int $week): array
    {
        return [
            'pagesNotFound' => $this->statsService->getPagesNotFound($year, $week),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/center/pages_not_found_chart.html.twig';
    }
}
