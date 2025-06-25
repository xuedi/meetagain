<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AdminController extends AbstractController
{
    public function __construct(private readonly TagAwareCacheInterface $appCache)
    {
        //
    }

    #[Route('/admin/dashboard/{year}/{week}', name: 'app_admin')]
    public function index(DashboardService $dashboard, ?int $year = null, ?int $week = null): Response
    {
        $dashboard->setTime($year, $week);

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'needForApproval' => $dashboard->getNeedForApproval(),
            'time' => $dashboard->getTimeControl(),
            'details' => $dashboard->getDetails(),
            'pagesNotFound' => $dashboard->getPagesNotFound(),
            'tests' => [
                'cache' => $this->cacheTest(),
            ],
        ]);
    }

    private function cacheTest(): bool
    {
        $expected = sprintf('This is a random number between 0 an 100: %d', random_int(0, 100));
        ;
        $cacheKey = 'app_admin_test';
        $this->appCache->delete($cacheKey);
        $this->appCache->get($cacheKey, fn() => $expected);
        $actual = $this->appCache->get($cacheKey, fn() => 'failed');

        return $expected === $actual;
    }
}
