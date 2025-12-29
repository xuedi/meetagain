<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardService;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly TagAwareCacheInterface $appCache,
        private readonly DashboardService $dashboard,
        private readonly Connection $connection,
        private readonly DependencyFactory $dependencyFactory,
        private readonly string $kernelProjectDir,
    ) {
    }

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    public function index(null|int $year = null, null|int $week = null): Response
    {
        $this->dashboard->setTime($year, $week);

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'needForApproval' => $this->dashboard->getNeedForApproval(),
            'time' => $this->dashboard->getTimeControl(),
            'details' => $this->dashboard->getDetails(),
            'pagesNotFound' => $this->dashboard->getPagesNotFound(),
            'actionItems' => $this->dashboard->getActionItems(),
            'userStatusBreakdown' => $this->dashboard->getUserStatusBreakdown(),
            'activeUsers' => $this->dashboard->getActiveUsersCount(),
            'imageStats' => $this->dashboard->getImageStats(),
            'upcomingEvents' => $this->dashboard->getUpcomingEvents(3),
            'pastEventsNoPhotos' => $this->dashboard->getPastEventsWithoutPhotos(5),
            'recurringEvents' => $this->dashboard->getRecurringEventsCount(),
            'tests' => $this->runHealthChecks(),
        ]);
    }

    private function runHealthChecks(): array
    {
        return [
            'cache' => $this->testCache(),
            'database' => $this->testDatabase(),
            'storage' => $this->testStorage(),
            'logSize' => $this->testLogSize(),
            'migrations' => $this->testMigrations(),
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
            $this->appCache->get($cacheKey, fn() => $expected);
            $actual = $this->appCache->get($cacheKey, fn() => 'failed');
            $this->appCache->delete($cacheKey);

            return ['ok' => $expected === $actual];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function testDatabase(): array
    {
        try {
            $result = $this->connection->executeQuery('SELECT 1')->fetchOne();
            return ['ok' => $result === 1];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function testStorage(): array
    {
        $uploadDir = $this->kernelProjectDir . '/public/uploads';
        $writable = is_dir($uploadDir) && is_writable($uploadDir);

        return ['ok' => $writable, 'path' => $uploadDir];
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

    private function testMigrations(): array
    {
        try {
            $statusCalculator = $this->dependencyFactory->getMigrationStatusCalculator();
            $newMigrations = $statusCalculator->getNewMigrations();

            return [
                'ok' => count($newMigrations) === 0,
                'pending' => count($newMigrations),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
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
