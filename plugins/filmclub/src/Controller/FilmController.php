<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Form\FilmType;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\VoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/films')]
class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmRepository $filmRepository,
        private readonly VoteRepository $voteRepository,
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('/', name: 'app_filmclub_filmlist', methods: ['GET'])]
    public function films(): Response
    {
        $films = $this->filmRepository->findAll();
        $filmEvents = $this->buildFilmEventMap();

        return $this->render('@Filmclub/film/list.html.twig', [
            'films' => $films,
            'filmEvents' => $filmEvents,
        ]);
    }

    /**
     * @return array<int, \App\Entity\Event>
     */
    private function buildFilmEventMap(): array
    {
        $filmEvents = [];
        $closedVotes = $this->voteRepository->findClosedVotes();

        foreach ($closedVotes as $vote) {
            $winningFilm = $vote->getWinningFilm();
            if ($winningFilm !== null && $winningFilm->getId() !== null) {
                $event = $this->eventRepository->find($vote->getEventId());
                if ($event !== null) {
                    $filmEvents[$winningFilm->getId()] = $event;
                }
            }
        }

        return $filmEvents;
    }

    #[Route('/new', name: 'app_filmclub_film_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $film = new Film();
        $form = $this->createForm(FilmType::class, $film);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $film->setCreatedAt(new \DateTimeImmutable());
            $film->setCreatedBy($this->getAuthedUser()->getId());

            $this->filmRepository->save($film, true);

            return $this->redirectToRoute('app_filmclub_filmlist');
        }

        return $this->render('@Filmclub/film/new.html.twig', [
            'film' => $film,
            'form' => $form,
        ]);
    }
}
