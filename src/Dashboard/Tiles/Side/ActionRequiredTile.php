<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use App\Service\DashboardActionService;

#[RequiresRole(UserRole::Admin)]
readonly class ActionRequiredTile implements DashboardSideTileInterface
{
    public function __construct(
        private DashboardActionService $actionService,
    ) {}

    public function getKey(): string
    {
        return 'action_required';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user): array
    {
        return [
            'actionItems' => $this->actionService->getActionItems(),
            'unverifiedCount' => $this->actionService->getUnverifiedCount(),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/action_required.html.twig';
    }
}
