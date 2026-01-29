<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Service\DashboardActionService;

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
        $contextLabel = $group && method_exists($group, 'getName') ? $group->getName() : 'All Events';

        return [
            'upcomingEvents' => $this->actionService->getUpcomingEvents(3, $group),
            'contextLabel' => $contextLabel,
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/upcoming_events.html.twig';
    }
}
