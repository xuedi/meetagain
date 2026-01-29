<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Center;

use App\Dashboard\DashboardCenterTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use App\Service\DashboardStatsService;

#[RequiresRole(UserRole::Admin)]
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

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user, int $year, int $week): array
    {
        return [
            'loginTrend' => $this->statsService->getLoginTrend($year, $week),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/center/login_activity_chart.html.twig';
    }
}
