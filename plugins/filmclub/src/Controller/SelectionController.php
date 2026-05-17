<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Filmclub\Activity\Messages\FilmSelectedForEvent;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\SelectionService;
use Plugin\Filmclub\Service\WishlistService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/manage')]
#[IsGranted('ROLE_ORGANIZER')]
final class SelectionController extends AbstractController
{
    public function __construct(
        private readonly SelectionService $selectionService,
        private readonly FilmService $filmService,
        private readonly EventRepository $eventRepo,
        private readonly ActivityService $activityService,
        private readonly WishlistService $wishlistService,
    ) {}

    #[Route('/select/{eventId}', name: 'app_plugin_filmclub_manage_select', methods: ['GET'])]
    public function selectForm(int $eventId): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        return $this->render('@Filmclub/manage/select.html.twig', [
            'event' => $event,
            'films' => $this->filmService->getList(),
            'currentSelection' => $this->selectionService->getForEvent($eventId),
        ]);
    }

    #[Route('/select/{eventId}/{filmId}', name: 'app_plugin_filmclub_manage_select_film', methods: ['POST'])]
    public function select(int $eventId, int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->selectionService->selectForEvent($eventId, $film, $user->getId());
            $this->activityService->log(FilmSelectedForEvent::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
                'event_id' => $eventId,
            ]);
            $this->addFlash('success', 'filmclub_manage.flash_film_selected');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_views_list');
    }

    #[Route('/choose-directly/{eventId}', name: 'app_plugin_filmclub_choose_directly', methods: ['GET'])]
    public function chooseDirectlyForm(int $eventId): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $poolFilms = array_column($this->wishlistService->aggregateByFilm(), 'film');

        return $this->render('@Filmclub/manage/choose_directly.html.twig', [
            'event' => $event,
            'films' => $poolFilms,
        ]);
    }

    #[Route('/choose-directly/{eventId}/{filmId}', name: 'app_plugin_filmclub_choose_directly_submit', methods: ['POST'])]
    public function chooseDirectly(int $eventId, int $filmId, Request $request): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->selectionService->chooseDirectly($event, $film, $user->getId());
            $this->activityService->log(FilmSelectedForEvent::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
                'event_id' => $eventId,
            ]);
            $this->addFlash('success', 'filmclub_tile.flash_chosen');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_views_list');
    }

    #[Route('/history', name: 'app_plugin_filmclub_views_list', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function history(): Response
    {
        $selections = $this->selectionService->getHistory();
        $films = [];
        $shownAtByFilmId = [];
        foreach ($selections as $selection) {
            $film = $selection->getFilm();
            $films[] = $film;
            $shownAtByFilmId[$film->getId()] = $selection->getSelectedAt();
        }

        return $this->render('@Filmclub/views/list.html.twig', [
            'films' => $films,
            'shownAtByFilmId' => $shownAtByFilmId,
        ]);
    }
}
