<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use App\Service\DashboardActionService;
use App\Service\DashboardStatsService;

#[RequiresRole(UserRole::Admin)]
readonly class RecentActivityTile implements DashboardSideTileInterface
{
    public function __construct(
        private DashboardStatsService $statsService,
        private DashboardActionService $actionService,
    ) {}

    public function getKey(): string
    {
        return 'recent_activity';
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user): array
    {
        // Get current week stats
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $week = (int) $now->format('W');

        return [
            'rsvpStats' => $this->statsService->getRsvpStats($year, $week),
            'socialStats' => $this->statsService->getSocialNetworkStats($year, $week),
            'messageStats' => $this->actionService->getMessageStats(),
            'contextLabel' => 'Platform',
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/recent_activity.html.twig';
    }
}
