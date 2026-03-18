<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\Admin\DashboardActionService;
use App\Service\Admin\DashboardStatsService;
use App\Service\System\HealthCheckService;
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
    ) {}

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    #[IsGranted('ROLE_ORGANIZER')]
    public function index(?int $year = null, ?int $week = null): Response
    {
        $now = new DateTime();
        $year ??= (int) $now->format('Y');
        $week ??= (int) $now->format('W');

        // Fetch rsvpStats once (shared by center RSVP table and side Recent Activity)
        $rsvpStats = $this->dashboardStats->getRsvpStats($year, $week);

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'time' => $this->dashboardStats->getTimeControl($year, $week),
            // Center tiles data
            'loginTrend' => $this->dashboardStats->getLoginTrend($year, $week),
            'rsvpStats' => $rsvpStats,
            'pagesNotFound' => $this->dashboardStats->getPagesNotFound($year, $week),
            // Side tiles data
            'actionItems' => $this->dashboardAction->getActionItems(),
            'unverifiedCount' => $this->dashboardAction->getUnverifiedCount(),
            'upcomingEvents' => $this->dashboardAction->getUpcomingEvents(3),
            'details' => $this->dashboardStats->getDetails($year, $week),
            'activeUsers' => $this->dashboardAction->getActiveUsersCount(),
            'recurringEvents' => $this->dashboardAction->getRecurringEventsCount(),
            'socialStats' => $this->dashboardStats->getSocialNetworkStats($year, $week),
            'messageStats' => $this->dashboardAction->getMessageStats(),
            'tests' => $this->healthCheckService->runAll(),
        ]);
    }
}
