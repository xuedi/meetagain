<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Center;

use App\Dashboard\DashboardCenterTileInterface;
use App\Entity\User;
use App\Service\DashboardStatsService;

readonly class LoginActivityChartTile implements DashboardCenterTileInterface
{
    public function __construct(
        private DashboardStatsService $statsService,
    ) {}

    public function getKey(): string
    {
        return 'login_activity_chart';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function isAccessible(User $user, ?object $group): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function getData(User $user, ?object $group, int $year, int $week): array
    {
        return [
            'loginTrend' => $this->statsService->getLoginTrend($year, $week, $group),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/center/login_activity_chart.html.twig';
    }
}
