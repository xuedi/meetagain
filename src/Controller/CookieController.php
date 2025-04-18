<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Service\DashboardService;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cookie')]
class CookieController extends AbstractController
{
    #[Route('/', name: 'app_cookie_overview')]
    public function overviewIndex(ConfigRepository $repo): Response
    {
        return $this->render('cookie/index.html.twig', [
            'config' => $repo->findAll(),
        ]);
    }

    #[Route('/update', name: 'app_cookie_update', methods: ['POST'])]
    public function updateIndex(ConfigRepository $repo): Response
    {
        return $this->render('cookie/index.html.twig', [
            'config' => $repo->findAll(),
        ]);
    }
}
