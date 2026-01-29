<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Service\HealthCheckService;

readonly class HealthChecksTile implements DashboardSideTileInterface
{
    public function __construct(
        private HealthCheckService $healthCheckService,
    ) {}

    public function getKey(): string
    {
        return 'health_checks';
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function isAccessible(User $user, ?object $group): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function getData(User $user, ?object $group): array
    {
        return [
            'tests' => $this->healthCheckService->runAll(),
        ];
    }

    public function getTemplate(): string
    {
        return 'admin/tiles/side/health_checks.html.twig';
    }
}
