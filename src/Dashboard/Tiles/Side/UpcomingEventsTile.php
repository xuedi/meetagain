<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use App\Service\DashboardActionService;

#[RequiresRole(UserRole::Admin)]
readonly class UpcomingEventsTile implements DashboardSideTileInterface
{
    public function __construct(
        private DashboardActionService $actionService,
    ) {}

    public function getKey(): string
    {
        return 'upcoming_events';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user): array
    {
        return [
            'upcomingEvents' => $this->actionService->getUpcomingEvents(3),
            'contextLabel' => 'All Events',
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/upcoming_events.html.twig';
    }
}
