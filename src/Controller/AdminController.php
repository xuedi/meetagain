<?php declare(strict_types=1);

namespace App\Controller;

use App\Security\Voter\DashboardVoter;
use App\Service\DashboardActionService;
use App\Service\DashboardService;
use App\Service\DashboardStatsService;
use App\Service\HealthCheckService;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly DashboardStatsService $dashboardStats,
        private readonly DashboardActionService $dashboardAction,
        private readonly HealthCheckService $healthCheckService,
        private readonly DashboardService $dashboardService,
        private readonly ?object $groupContextService = null,
    ) {}

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    #[IsGranted(DashboardVoter::ACCESS)]
    public function index(?int $year = null, ?int $week = null): Response
    {
        $user = $this->getAuthedUser();
        $now = new DateTime();
        $year ??= (int) $now->format('Y');
        $week ??= (int) $now->format('W');

        // Get group context (null for admins without context, or selected group)
        $groupContext = null;
        if ($this->groupContextService && method_exists($this->groupContextService, 'getCurrentContext')) {
            $context = $this->groupContextService->getCurrentContext();
            $groupContext = $context->group ?? null;
        }

        // Get accessible center tiles (time-series)
        $centerTiles = $this->dashboardService->getCenterTilesForUser($user, $groupContext);
        $centerTileData = [];
        foreach ($centerTiles as $tile) {
            $centerTileData[] = [
                'template' => $tile->getTemplate(),
                'data' => $tile->getData($user, $groupContext, $year, $week),
            ];
        }

        // Get accessible side tiles (fixed info)
        $sideTiles = $this->dashboardService->getSideTilesForUser($user, $groupContext);
        $sideTileData = [];
        foreach ($sideTiles as $tile) {
            $sideTileData[] = [
                'template' => $tile->getTemplate(),
                'data' => $tile->getData($user, $groupContext),
            ];
        }

        // Get managed groups for context switcher (future enhancement)
        $managedGroups = [];
        if ($this->groupContextService && method_exists($this->groupContextService, 'getManagedGroupsForUser')) {
            $managedGroups = $this->groupContextService->getManagedGroupsForUser($user);
        }

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'centerTiles' => $centerTileData,
            'sideTiles' => $sideTileData,
            'managedGroups' => $managedGroups,
            'currentGroup' => $groupContext,
            'time' => $this->dashboardStats->getTimeControl($year, $week),
        ]);
    }
}
