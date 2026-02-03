<?php declare(strict_types=1);

namespace App\Dashboard\Tiles\Side;

use App\Dashboard\DashboardSideTileInterface;
use App\Entity\User;
use App\Entity\UserRole;
use App\Service\HealthCheckService;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
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

    public function isAccessible(User $user): bool
    {
        return $user->hasUserRole(UserRole::Admin);
    }

    public function getData(User $user): array
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
