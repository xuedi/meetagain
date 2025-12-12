<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ValueObjects\LogEntry;
use App\Repository\NotFoundLogRepository;
use App\Service\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminLogsController extends AbstractController
{
    public function __construct(private readonly \App\Service\ActivityService $activityService, private readonly \App\Repository\NotFoundLogRepository $foundLogRepo)
    {
    }
    #[Route('/admin/logs/activity', name: 'app_admin_logs_activity')]
    public function activityList(): Response
    {
        return $this->render('admin/logs/activity_list.html.twig', [
            'active' => 'activity',
            'activities' => $this->activityService->getAdminList(),
        ]);
    }

    #[Route('/admin/logs/system', name: 'app_admin_logs_system')]
    public function systemLogs(int $id = 0): Response
    {
        return $this->render('admin/logs/system_list.html.twig', [
            'active' => 'logs',
            'logs' => $this->getLogs(),
        ]);
    }

    #[Route('/admin/logs/404', name: 'app_admin_logs_not_found')]
    public function notFoundLogs(): Response
    {
        return $this->render('admin/logs/notFound_list.html.twig', [
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
