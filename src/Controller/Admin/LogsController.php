<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminLink;
use App\Entity\ValueObjects\LogEntry;
use App\Repository\NotFoundLogRepository;
use App\Service\ActivityService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LogsController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(section: 'Logs', links: [
            new AdminLink(
                label: 'menu_admin_logs_activity',
                route: 'app_admin_activity_log',
                active: 'activity',
                role: 'ROLE_ADMIN',
            ),
            new AdminLink(
                label: 'menu_admin_logs_system',
                route: 'app_admin_system_log',
                active: 'logs',
                role: 'ROLE_ADMIN',
            ),
            new AdminLink(
                label: 'menu_admin_logs_404',
                route: 'app_admin_not_found_log',
                active: '404',
                role: 'ROLE_ADMIN',
            ),
        ]);
    }

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly NotFoundLogRepository $foundLogRepo,
    ) {}

    #[Route('/admin/logs/activity', name: 'app_admin_activity_log')]
    public function activityList(): Response
    {
        return $this->render('admin/logs/logs_activity_list.html.twig', [
            'active' => 'activity',
            'activities' => $this->activityService->getAdminList(),
        ]);
    }

    #[Route('/admin/logs/system', name: 'app_admin_system_log')]
    public function systemLogs(int $id = 0): Response
    {
        return $this->render('admin/logs/logs_system_list.html.twig', [
            'active' => 'logs',
            'logs' => $this->getLogs(),
        ]);
    }

    #[Route('/admin/logs/404', name: 'app_admin_not_found_log')]
    public function notFoundLogs(): Response
    {
        return $this->render('admin/logs/logs_notFound_list.html.twig', [
            'active' => '404',
            'list' => $this->foundLogRepo->getTop100(),
        ]);
    }

    private function getLogList(): array
    {
        $list = [];
        $logPath = dirname(__DIR__, 3) . '/var/log/';
        $logFiles = glob($logPath . '/*.log');

        foreach ($logFiles as $logFile) {
            $list[] = $logFile;
        }

        return $list;
    }

    private function getLogs(): array
    {
        $logList = [];
        foreach ($this->getLogList() as $logFile) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if ($line === '' || $line === '0') {
                    continue;
                }
                $logList[] = LogEntry::fromString($line);
            }
        }

        return $logList;
    }
}
