<?php declare(strict_types=1);

namespace App\AdminModules\Logs;

use App\Entity\ValueObjects\LogEntry;
use App\Repository\NotFoundLogRepository;
use App\Service\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LogsController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly NotFoundLogRepository $foundLogRepo,
    ) {}

    public function activityList(): Response
    {
        return $this->render('admin_modules/logs/logs_activity_list.html.twig', [
            'active' => 'activity',
            'activities' => $this->activityService->getAdminList(),
        ]);
    }

    public function systemLogs(int $id = 0): Response
    {
        return $this->render('admin_modules/logs/logs_system_list.html.twig', [
            'active' => 'logs',
            'logs' => $this->getLogs(),
        ]);
    }

    public function notFoundLogs(): Response
    {
        return $this->render('admin_modules/logs/logs_notFound_list.html.twig', [
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
