<?php declare(strict_types=1);

namespace App\Controller;

use App\Admin\Dashboard\ChartTile;
use App\Admin\Dashboard\CounterTile;
use App\Admin\Dashboard\DashboardTile;
use App\Admin\Dashboard\HealthTile;
use App\Admin\Dashboard\ListTile;
use App\Admin\Dashboard\MultiSeriesChartTile;
use App\Admin\Dashboard\TableTile;
use App\Admin\Dashboard\TileDataset;
use App\Admin\Dashboard\TileHealthCheck;
use App\Admin\Dashboard\TileListItem;
use App\Admin\Dashboard\TileRow;
use App\Filter\Admin\Dashboard\DashboardScope;
use App\Filter\Admin\Dashboard\DashboardScopeFilterService;
use App\Repository\EventRepository;
use App\Service\Admin\DashboardActionService;
use App\Service\Admin\DashboardStatsService;
use App\Service\System\HealthCheckService;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ORGANIZER')]
final class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly DashboardStatsService $dashboardStats,
        private readonly DashboardActionService $dashboardAction,
        private readonly HealthCheckService $healthCheckService,
        private readonly DashboardScopeFilterService $scopeFilterService,
        private readonly EventRepository $eventRepo,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    public function index(Request $request, ?int $year = null, ?int $week = null): Response
    {
        $now = new DateTime();
        $year ??= (int) $now->format('Y');
        $week ??= (int) $now->format('W');

        $scope = $this->scopeFilterService->resolveScope();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isSteward = $this->isGranted('ROLE_STEWARD');
        $isOrganizer = $this->isGranted('ROLE_ORGANIZER');
        $isPlatform = $scope->isPlatformWide();

        $centerTiles = [];
        $sideTiles = [];

        if ($isAdmin && $isPlatform) {
            $this->buildAdminPlatform($year, $week, $centerTiles, $sideTiles);
        } else {
            $this->buildScopedTiles($year, $week, $scope, $isOrganizer, $isSteward, $request->getLocale(), $centerTiles, $sideTiles);
        }

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'time' => $this->dashboardStats->getTimeControl($year, $week),
            'centerTiles' => $centerTiles,
            'sideTiles' => $sideTiles,
        ]);
    }

    /**
     * @param list<DashboardTile> $centerTiles
     * @param list<DashboardTile> $sideTiles
     */
    private function buildAdminPlatform(int $year, int $week, array &$centerTiles, array &$sideTiles): void
    {
        $activity = $this->dashboardStats->getActivityTrend($year, $week);
        $centerTiles[] = new MultiSeriesChartTile(
            title: 'admin_shell.dashboard_platform_activity_title',
            canvasId: 'platformActivityChart',
            labels: $activity['labels'],
            datasets: [
                new TileDataset($this->translator->trans('admin_shell.dashboard_series_logins'), $activity['logins'], 'rgba(54, 162, 235, 1)'),
                new TileDataset($this->translator->trans('admin_shell.dashboard_series_rsvps'), $activity['rsvps'], 'rgba(75, 192, 75, 1)'),
                new TileDataset($this->translator->trans('admin_shell.dashboard_series_new_members'), $activity['newMembers'], 'rgba(255, 159, 64, 1)'),
            ],
        );

        $pagesNotFound = $this->dashboardStats->getPagesNotFound($year, $week);
        $notFoundDataset = [];
        foreach ($pagesNotFound['list'] as $day => $count) {
            $notFoundDataset[] = ['x' => $day, 'y' => $count];
        }
        $centerTiles[] = new ChartTile(
            title: 'admin_shell.dashboard_not_found_title',
            canvasId: 'notFoundChart',
            dataset: $notFoundDataset,
            color: 'rgba(255, 99, 132, 0.5)',
        );

        $details = $this->dashboardStats->getDetails($year, $week);
        $statsRows = [];
        foreach ($details as $key => $data) {
            $statsRows[] = new TileRow([
                $this->translator->trans('admin_shell.dashboard_stats_metric_' . $key),
                (int) $data['count'],
                (int) $data['week'],
            ]);
        }
        $statsRows[] = new TileRow([
            $this->translator->trans('admin_shell.dashboard_stats_active'),
            $this->dashboardAction->getActiveUsersCount(),
            '',
        ], highlight: true);
        $statsRows[] = new TileRow([
            $this->translator->trans('admin_shell.dashboard_stats_recurring'),
            $this->dashboardAction->getSeriesCount(),
            '',
        ]);
        $sideTiles[] = new TableTile(
            title: 'admin_shell.dashboard_stats_title',
            rows: $statsRows,
            headers: [
                'admin_shell.dashboard_stats_metric',
                'admin_shell.dashboard_stats_all',
                'admin_shell.dashboard_stats_week',
            ],
        );

        $actionItems = $this->dashboardAction->getActionItems();
        $sideTiles[] = new TableTile(title: 'admin_shell.dashboard_action_items_title', rows: [
            new TileRow([
                $this->translator->trans('admin_shell.dashboard_action_reported_images'),
                $actionItems['reportedImages'],
            ]),
            new TileRow([
                $this->translator->trans('admin_shell.dashboard_action_stale_emails'),
                $actionItems['staleEmails'],
            ]),
            new TileRow([
                $this->translator->trans('admin_shell.dashboard_action_pending_emails'),
                $actionItems['pendingEmails'],
            ]),
        ]);

        $tests = $this->healthCheckService->runAll();
        $sideTiles[] = new HealthTile(title: 'admin_shell.dashboard_health_title', checks: [
            new TileHealthCheck('admin_shell.dashboard_health_cache', (bool) ($tests['cache']['ok'] ?? false)),
            new TileHealthCheck(
                'admin_shell.dashboard_health_log_size',
                (bool) ($tests['logSize']['ok'] ?? false),
                sprintf('%.1fMB', (($tests['logSize']['size'] ?? 0) / 1024) / 1024),
            ),
            new TileHealthCheck(
                'admin_shell.dashboard_health_disk_space',
                (bool) ($tests['diskSpace']['ok'] ?? false),
                ($tests['diskSpace']['percentFree'] ?? 0) . '% free',
            ),
            new TileHealthCheck('admin_shell.dashboard_health_php_version', true, $tests['phpVersion']['version'] ?? ''),
        ]);
    }

    /**
     * @param list<DashboardTile> $centerTiles
     * @param list<DashboardTile> $sideTiles
     */
    private function buildScopedTiles(
        int $year,
        int $week,
        DashboardScope $scope,
        bool $isOrganizer,
        bool $isSteward,
        string $locale,
        array &$centerTiles,
        array &$sideTiles,
    ): void {
        if ($isOrganizer) {
            $rsvpYesTrend = $this->dashboardStats->getRsvpYesTrend($year, $week, $scope);
            $rsvpDataset = [];
            foreach ($rsvpYesTrend as $day => $count) {
                $rsvpDataset[] = ['x' => substr($day, 0, 3), 'y' => $count];
            }
            $centerTiles[] = new ChartTile(
                title: 'admin_shell.dashboard_rsvp_title',
                canvasId: 'rsvpYesChart',
                dataset: $rsvpDataset,
                color: 'rgba(75, 192, 75, 0.6)',
            );
        }

        if ($isSteward) {
            $lowRsvpEvents = $this->dashboardAction->getUpcomingEventsLowRsvp(14, 3, $scope);
            $lowRsvpItems = [];
            foreach ($lowRsvpEvents as $event) {
                $lowRsvpItems[] = new TileListItem(
                    label: $event->getTitle($locale) ?: $event->getTitle('en') ?: '#' . $event->getId(),
                    sublabel: $event->getStart()->format('Y-m-d'),
                    link: $this->generateUrl('app_event_details', ['id' => $event->getId()]),
                );
            }
            $centerTiles[] = new ListTile(
                title: 'admin_shell.dashboard_low_rsvp_title',
                items: $lowRsvpItems,
                emptyMessage: 'admin_shell.dashboard_low_rsvp_empty',
            );
        }

        if ($isOrganizer) {
            $pastEvents = $this->eventRepo->getPastEvents(3, $scope?->eventIds());
            $pastItems = [];
            $rsvpsLabel = $this->translator->trans('admin_shell.dashboard_past_events_rsvps');
            foreach ($pastEvents as $event) {
                $rsvpCount = count($event->getRsvp());
                $location = $event->getLocation()?->getName();
                $sublabelParts = [
                    $event->getStart()->format('Y-m-d'),
                    sprintf('%d %s', $rsvpCount, $rsvpsLabel),
                ];
                if ($location !== null && $location !== '') {
                    $sublabelParts[] = $location;
                }
                $pastItems[] = new TileListItem(
                    label: $event->getTitle($locale) ?: $event->getTitle('en') ?: '#' . $event->getId(),
                    sublabel: implode(' · ', $sublabelParts),
                    link: $this->generateUrl('app_event_details', ['id' => $event->getId()]),
                );
            }
            $centerTiles[] = new ListTile(
                title: 'admin_shell.dashboard_past_events_title',
                items: $pastItems,
                emptyMessage: 'admin_shell.dashboard_past_events_empty',
            );
        }

        if ($isSteward) {
            $sideTiles[] = new CounterTile(
                title: 'admin_shell.dashboard_members_this_week',
                value: $this->dashboardAction->getMembersThisWeek($year, $week, $scope),
                icon: 'user-plus',
            );
        }

        if ($isOrganizer) {
            $sideTiles[] = new CounterTile(
                title: 'admin_shell.dashboard_stats_recurring',
                value: $this->dashboardAction->getSeriesCount($scope),
                icon: 'sync-alt',
            );
        }

        $messageStats = $this->dashboardAction->getMessageStats($scope);
        $socialStats = $this->dashboardStats->getSocialNetworkStats($year, $week, $scope);
        $sideTiles[] = new TableTile(title: 'admin_shell.dashboard_activity_title', rows: [
            new TileRow([
                $this->translator->trans('admin_shell.dashboard_activity_connections'),
                $socialStats['total'],
            ]),
            new TileRow([$this->translator->trans('admin_shell.dashboard_activity_messages'), $messageStats['total']]),
        ]);
    }
}
