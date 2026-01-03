<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use Plugin\Filmclub\Repository\FilmRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/films')]
class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmRepository $filmRepository,
    ) {
    }

    #[Route('/', name: 'app_filmclub_filmlist', methods: ['GET'])]
    public function films(): Response
    {
        return $this->render('@Filmclub/film/list.html.twig', [
            'films' => $this->filmRepository->findAll(),
        ]);
    }
}
