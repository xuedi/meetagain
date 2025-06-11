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
    #[Route('/admin/logs/activity', name: 'app_admin_logs_activity')]
    public function activityList(ActivityService $activityService): Response
    {
        return $this->render('admin/logs/activity_list.html.twig', [
            'active' => 'activity',
            'activities' => $activityService->getAdminList(),
        ]);
    }

    #[Route('/admin/logs/system/{id}', name: 'app_admin_logs_system')]
    public function systemLogs(int $id = 0): Response
    {
        $logs = $this->getLogList();
        return $this->render('admin/logs/system_list.html.twig', [
            'active' => 'logs',
            'content' => $logs === [] ? '' : $this->getLogContent($logs[$id]['source']),
            'logs' => $logs,
        ]);
    }

    #[Route('/admin/logs/404', name: 'app_admin_logs_not_found')]
    public function notFoundLogs(NotFoundLogRepository $foundLogRepo): Response
    {
        $logs = $this->getLogList();
        return $this->render('admin/logs/notFound_list.html.twig', [
            'active' => '404',
            'list' => $foundLogRepo->getTop100(),
        ]);
    }

    private function getLogList(): array
    {
        $list = [];
        $logPath = dirname(__DIR__, 3) . '/var/log/';
        $logFiles = glob($logPath . '/*.log');

        foreach ($logFiles as $logFile) { // TODO: turn into array map function
            $nameChunks = explode('/', $logFile);
            $list[] = [
                'name' => end($nameChunks),
                'source' => $logFile,
            ];
        }

        return $list;
    }

    // TODO: add a level filter and split content with parser like: https://packagist.org/packages/innmind/log-reader
    private function getLogContent(string $path): array
    {
        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        $logList = [];
        foreach ($lines as $line) {
            if ($line === '' || $line === '0') {
                continue;
            }
            $logList[] = LogEntry::fromString($line);
        }

        return $logList;
    }
}
