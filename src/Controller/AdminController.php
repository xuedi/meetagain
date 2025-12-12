<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AdminController extends AbstractController
{
    public const string ROUTE_ADMIN = 'app_admin';

    public function __construct(
        private readonly TagAwareCacheInterface $appCache,
        private readonly \App\Service\DashboardService $dashboard,
    ) {
        //
    }

    #[Route('/admin/dashboard/{year}/{week}', name: self::ROUTE_ADMIN)]
    public function index(null|int $year = null, null|int $week = null): Response
    {
        $this->dashboard->setTime($year, $week);

        return $this->render('admin/index.html.twig', [
            'active' => 'dashboard',
            'needForApproval' => $this->dashboard->getNeedForApproval(),
            'time' => $this->dashboard->getTimeControl(),
            'details' => $this->dashboard->getDetails(),
            'pagesNotFound' => $this->dashboard->getPagesNotFound(),
            'tests' => [
                'cache' => $this->cacheTest(),
            ],
        ]);
    }

    private function cacheTest(): bool
    {
        $expected = sprintf('This is a random number between 0 an 100: %d', random_int(0, 100));
        $cacheKey = 'app_admin_test';
        $this->appCache->delete($cacheKey);
        $this->appCache->get($cacheKey, fn() => $expected);
        $actual = $this->appCache->get($cacheKey, fn() => 'failed');

        return $expected === $actual;
    }
}
