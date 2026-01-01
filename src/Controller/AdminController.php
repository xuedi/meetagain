<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardActionService;
use App\Service\DashboardStatsService;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly TagAwareCacheInterface $appCache,
        private readonly DashboardStatsService $dashboardStats,
        private readonly DashboardActionService $dashboardAction,
        private readonly string $kernelProjectDir,
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
            'tests' => $this->runHealthChecks(),
        ]);
    }

    private function runHealthChecks(): array
    {
        return [
            'cache' => $this->testCache(),
            'logSize' => $this->testLogSize(),
            'diskSpace' => $this->testDiskSpace(),
            'phpVersion' => $this->getPhpInfo(),
        ];
    }

    private function testCache(): array
    {
        try {
            $expected = sprintf('test_%d', random_int(0, 100));
            $cacheKey = 'app_admin_health_test';
            $this->appCache->delete($cacheKey);
            $this->appCache->get($cacheKey, fn () => $expected);
            $actual = $this->appCache->get($cacheKey, fn () => 'failed');
            $this->appCache->delete($cacheKey);

            return ['ok' => $expected === $actual];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function testLogSize(): array
    {
        $logFile = $this->kernelProjectDir . '/var/log/dev.log';
        $maxSize = 50 * 1024 * 1024; // 50MB

        if (!file_exists($logFile)) {
            return ['ok' => true, 'size' => 0, 'maxSize' => $maxSize];
        }

        $size = filesize($logFile);

        return [
            'ok' => $size < $maxSize,
            'size' => $size,
            'maxSize' => $maxSize,
        ];
    }

    private function testDiskSpace(): array
    {
        $path = $this->kernelProjectDir;
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false) {
            return ['ok' => false, 'error' => 'Could not determine disk space'];
        }

        $percentFree = ($free / $total) * 100;

        return [
            'ok' => $percentFree > 10,
            'free' => $free,
            'total' => $total,
            'percentFree' => round($percentFree, 1),
        ];
    }

    private function getPhpInfo(): array
    {
        return [
            'ok' => PHP_VERSION_ID >= 80200,
            'version' => PHP_VERSION,
            'memoryLimit' => ini_get('memory_limit'),
            'maxExecution' => ini_get('max_execution_time'),
        ];
    }
}
