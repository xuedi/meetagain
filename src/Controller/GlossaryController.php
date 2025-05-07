<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Service\DashboardService;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary')]
class GlossaryController extends AbstractController
{
    #[Route('/', name: 'app_glossary')]
    public function index(): Response
    {
        return $this->render('glossary/index.html.twig');
    }
}
