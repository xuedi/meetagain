<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Center;

use App\Dashboard\DashboardCenterTileInterface;
use App\Entity\User;
use App\Service\DashboardStatsService;

readonly class RsvpTrendChartTile implements DashboardCenterTileInterface
{
    public function __construct(
        private DashboardStatsService $statsService,
    ) {}

    public function getKey(): string
    {
        return 'rsvp_trend_chart';
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function isAccessible(User $user, ?object $group): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function getData(User $user, ?object $group, int $year, int $week): array
    {
        return [
            'rsvpStats' => $this->statsService->getRsvpStats($year, $week, $group),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/center/rsvp_trend_chart.html.twig';
    }
}
