<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/films')]
class FilmController extends AbstractController
{
    public function __construct() {
    }

    #[Route('/', name: 'app_filmclub_filmlist', methods: ['GET'])]
    public function films(): Response
    {
        return $this->render('@Filmclub/film/list.html.twig', [
            'films' => [],
        ]);
    }
}
