<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CronLog;
use App\Repository\CronLogRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/cron')]
final class CronLogController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly CronLogRepository $cronLogRepository,
    ) {}

    #[Route('', name: 'app_admin_cron_log')]
    public function list(): Response
    {
        return $this->render('admin/logs/logs_cron_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'cron',
            'logs' => $this->cronLogRepository->findRecent(5000),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_cron_log_show')]
    public function show(CronLog $cronLog): Response
    {
        return $this->render('admin/logs/logs_cron_show.html.twig', [
            'active' => 'logs',
            'activeLog' => 'cron',
            'log' => $cronLog,
        ]);
    }
}
