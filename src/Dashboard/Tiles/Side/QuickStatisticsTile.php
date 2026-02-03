<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Service\DashboardActionService;
use App\Service\DashboardStatsService;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
readonly class QuickStatisticsTile implements DashboardSideTileInterface
{
    public function __construct(
        private DashboardStatsService $statsService,
        private DashboardActionService $actionService,
    ) {}

    public function getKey(): string
    {
        return 'quick_statistics';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user): array
    {
        // Use current week for stats
        $now = new \DateTime();
        $year = (int) $now->format('Y');
        $week = (int) $now->format('W');

        return [
            'details' => $this->statsService->getDetails($year, $week),
            'activeUsers' => $this->actionService->getActiveUsersCount(),
            'recurringEvents' => $this->actionService->getRecurringEventsCount(),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/quick_statistics.html.twig';
    }
}
