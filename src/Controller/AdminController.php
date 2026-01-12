<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardActionService;
use App\Service\DashboardStatsService;
use App\Service\HealthCheckService;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly DashboardStatsService $dashboardStats,
        private readonly DashboardActionService $dashboardAction,
        private readonly HealthCheckService $healthCheckService,
    ) {
    }

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    public function index(?int $year = null, ?int $week = null): Response
    {
        $now = new DateTime();
        $year ??= (int) $now->format('Y');
        $week ??= (int) $now->format('W');
        $dates = $this->dashboardStats->calculateDates($year, $week);

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'needForApproval' => $this->dashboardAction->getNeedForApproval(),
            'time' => $this->dashboardStats->getTimeControl($year, $week),
            'details' => $this->dashboardStats->getDetails($year, $week),
            'pagesNotFound' => $this->dashboardStats->getPagesNotFound($year, $week),
            'actionItems' => $this->dashboardAction->getActionItems(),
            'userStatusBreakdown' => $this->dashboardAction->getUserStatusBreakdown(),
            'activeUsers' => $this->dashboardAction->getActiveUsersCount(),
            'imageStats' => $this->dashboardAction->getImageStats($dates['start'], $dates['stop']),
            'upcomingEvents' => $this->dashboardAction->getUpcomingEvents(3),
            'pastEventsNoPhotos' => $this->dashboardAction->getPastEventsWithoutPhotos(5),
            'recurringEvents' => $this->dashboardAction->getRecurringEventsCount(),
            'tests' => $this->healthCheckService->runAll(),
            'unverifiedCount' => $this->dashboardAction->getUnverifiedCount(),
            'messageStats' => $this->dashboardAction->getMessageStats(),
            'emailQueueBreakdown' => $this->dashboardAction->getEmailQueueBreakdown(),
            'rsvpStats' => $this->dashboardStats->getRsvpStats($year, $week),
            'loginTrend' => $this->dashboardStats->getLoginTrend($year, $week),
            'socialStats' => $this->dashboardStats->getSocialNetworkStats($year, $week),
            'commandStats' => $this->dashboardAction->getCommandExecutionStats(),
            'lastCommands' => $this->dashboardAction->getLastCommandExecutions(),
            'emailDeliveryStats' => $this->dashboardAction->getEmailDeliveryStats(),
            'emailDeliveryRate' => $this->dashboardAction->getEmailDeliverySuccessRate(),
        ]);
    }
}
