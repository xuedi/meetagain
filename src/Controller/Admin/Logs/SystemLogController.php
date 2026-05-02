<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Tabs\AdminTabsInterface;
use App\ValueObject\LogEntry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/system')]
final class SystemLogController extends AbstractLogsController implements AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct($translator, 'system');
    }

    #[Route('', name: 'app_admin_system_log')]
    public function list(): Response
    {
        return $this->render('admin/logs/logs_system_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'logs',
            'logs' => $this->getLogs(),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * Only read the log file for the current environment. Mixing dev.log and test.log
     * in the same admin view would surface DEBUG/INFO entries from tests even when the
     * dev Monolog handler is capped at warning.
     */
    private function getLogList(): array
    {
        $logFile = dirname(__DIR__, 4) . '/var/log/' . $this->environment . '.log';

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
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $logList;
    }
}
