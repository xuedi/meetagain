<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Service\DashboardActionService;
use App\Service\DashboardStatsService;

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

    public function isAccessible(User $user, ?object $group): bool
    {
        // Both admin and group owners see this
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return $group !== null;
    }

    public function getData(User $user, ?object $group): array
    {
        // Get current week stats
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $week = (int) $now->format('W');

        $contextLabel = $group && method_exists($group, 'getName') ? $group->getName() : 'Platform';

        return [
            'rsvpStats' => $this->statsService->getRsvpStats($year, $week, $group),
            'socialStats' => $this->statsService->getSocialNetworkStats($year, $week),
            'messageStats' => $this->actionService->getMessageStats(),
            'contextLabel' => $contextLabel,
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/recent_activity.html.twig';
    }
}
