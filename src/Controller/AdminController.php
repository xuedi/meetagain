<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Service\DashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/{year}/{week}', name: 'app_admin')]
    public function index(DashboardService $dashboard, ?int $year = null, ?int $week = null): Response
    {
        $dashboard->setTime($year, $week);

        return $this->render('admin/index.html.twig', [
            'time' => $dashboard->getTimeControl(),
            'details' => $dashboard->getDetails(),
            'pagesNotFound' => $dashboard->getPagesNotFound(),
        ]);
    }
    #[Route('/admin/config', name: 'app_admin_config')]
    public function configIndex(ConfigRepository $repo): Response
    {
        return $this->render('admin/config.html.twig', [
            'config' => $repo->findAll(),
        ]);
    }
}
