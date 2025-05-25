<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/dashboard/{year}/{week}', name: 'app_admin')]
    public function index(DashboardService $dashboard, ?int $year = null, ?int $week = null): Response
    {
        $dashboard->setTime($year, $week);

        return $this->render('admin/index.html.twig', [
            'time' => $dashboard->getTimeControl(),
            'details' => $dashboard->getDetails(),
            'pagesNotFound' => $dashboard->getPagesNotFound(),
        ]);
    }
}
