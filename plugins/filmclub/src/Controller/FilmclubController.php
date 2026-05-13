<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/filmclub')]
final class FilmclubController extends AbstractController
{
    #[Route('', name: 'app_plugin_filmclub_landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('@Filmclub/landing.html.twig');
    }
}
