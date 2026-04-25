<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Entity\AdminLink;
use App\Repository\NotFoundLogRepository;
use App\ValueObject\LogEntry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs')]
final class LogsController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_logs',
                    route: 'app_admin_activity_log',
                    active: 'logs',
                    role: 'ROLE_ADMIN',
                ),
            ],
            sectionPriority: 100,
        );
    }

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly NotFoundLogRepository $foundLogRepo,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {}

    #[Route('', name: 'app_admin_logs')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_activity_log');
    }

    #[Route('/activity/{id}', name: 'app_admin_activity_log_show')]
    public function activityShow(int $id): Response
    {
        $activity = $this->activityService->getAdminDetail($id);
        if ($activity === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/logs/logs_activity_show.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activity' => $activity,
        ]);
    }

    #[Route('/activity', name: 'app_admin_activity_log')]
    public function activityList(): Response
    {
        return $this->render('admin/logs/logs_activity_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activities' => $this->activityService->getAdminList(),
        ]);
    }

    #[Route('/system', name: 'app_admin_system_log')]
    public function systemLogs(int $id = 0): Response
    {
        return $this->render('admin/logs/logs_system_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'logs',
            'logs' => $this->getLogs(),
        ]);
    }

    #[Route('/404', name: 'app_admin_not_found_log')]
    public function notFoundLogs(): Response
    {
        return $this->render('admin/logs/logs_notFound_list.html.twig', [
            'active' => 'logs',
            'activeLog' => '404',
            'list' => $this->foundLogRepo->getTop100(),
            'recent' => $this->foundLogRepo->getRecent(200),
        ]);
    }

    /**
     * Only read the log file for the current environment. Mixing dev.log and test.log
     * in the same admin view would surface DEBUG/INFO entries from tests even when the
     * dev Monolog handler is capped at warning.
     */
    private function getLogList(): array
    {
        $logFile = dirname(__DIR__, 3) . '/var/log/' . $this->environment . '.log';

        return is_file($logFile) ? [$logFile] : [];
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
                try {
                    $logList[] = LogEntry::fromString($line);
                } catch (\Throwable) {
                    continue; // skip malformed log lines
                }
            }
        }

        return $logList;
    }
}
