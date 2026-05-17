<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Entity\User;
use Plugin\Filmclub\Activity\Messages\FilmAdded;
use Plugin\Filmclub\Enum\ViewType;
use Plugin\Filmclub\Form\FilmEditType;
use Plugin\Filmclub\Form\FilmLookupType;
use Plugin\Filmclub\Form\FilmManualType;
use Plugin\Filmclub\Service\FilmLookupResolver;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\NoteService;
use Plugin\Filmclub\Service\SelectionService;
use Plugin\Filmclub\Service\ViewTypeResolver;
use Plugin\Filmclub\Service\WishlistService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/film')]
final class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmService $filmService,
        private readonly FilmLookupResolver $lookupResolver,
        private readonly WishlistService $wishlistService,
        private readonly NoteService $noteService,
        private readonly SelectionService $selectionService,
        private readonly ActivityService $activityService,
        private readonly ViewTypeResolver $viewTypeResolver,
    ) {}

    #[Route('', name: 'app_filmclub_filmlist', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Filmclub/film/list.html.twig', [
            'films' => $this->filmService->getList(),
            'viewContext' => 'films',
            'currentView' => $this->viewTypeResolver->get('films', ViewType::List),
            'availableViews' => ViewType::cases(),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_filmclub_film_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $film = $this->filmService->get($id);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getUser();
        $authedUser = $user instanceof User ? $user : null;
        $isWishlisted = $authedUser !== null && $this->wishlistService->isWishlisted($film, $authedUser->getId());
        $wanterCount = $this->wishlistService->getWanterCountForFilm($film);
        $userNote = $authedUser !== null ? $this->noteService->getNoteForUser($authedUser->getId(), $film) : null;
        $revealedNotes = $authedUser !== null ? $this->noteService->getRevealedForFilm($film) : [];
        $selections = $this->selectionService->getSelectionsForFilm($film);

        return $this->render('@Filmclub/film/detail.html.twig', [
            'film' => $film,
            'isWishlisted' => $isWishlisted,
            'wanterCount' => $wanterCount,
            'userNote' => $userNote,
            'revealedNotes' => $revealedNotes,
            'selections' => $selections,
        ]);
    }

    #[Route('/lookup', name: 'app_plugin_filmclub_film_lookup', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function lookup(Request $request): Response
    {
        $adapter = $this->lookupResolver->resolve();

        if ($adapter === null) {
            $this->addFlash('info', 'filmclub_film.flash_no_adapter');
            return $this->redirectToRoute('app_plugin_filmclub_film_manual');
        }

        $form = $this->createForm(FilmLookupType::class, null, ['method' => 'GET']);
        $form->handleRequest($request);

        $results = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $query = $form->get('query')->getData();
            $year = $form->get('year')->getData();
            $locale = $request->getLocale();

            try {
                $results = $adapter->searchByTitle($query, $year, $locale);
            } catch (\Throwable) {
                $this->addFlash('error', 'filmclub_film.flash_lookup_error');
            }
        }

        return $this->render('@Filmclub/film/lookup.html.twig', [
            'form' => $form,
            'results' => $results,
        ]);
    }

    #[Route('/import', name: 'app_plugin_filmclub_film_import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(Request $request): Response
    {
        $externalId = (string) $request->request->get('externalId', '');
        $source = (string) $request->request->get('source', '');

        if ($externalId === '' || $source === '') {
            throw $this->createNotFoundException();
        }

        $adapter = $this->lookupResolver->resolve();

        if ($adapter === null) {
            $this->addFlash('error', 'filmclub_film.flash_no_adapter');
            return $this->redirectToRoute('app_plugin_filmclub_film_manual');
        }

        $metadata = $adapter->fetchById($externalId, $request->getLocale());
        if ($metadata === null) {
            $this->addFlash('error', 'filmclub_film.flash_not_found');
            return $this->redirectToRoute('app_plugin_filmclub_film_lookup');
        }

        $user = $this->getAuthedUser();

        try {
            $film = $this->filmService->createFromMetadata($metadata, $user->getId());

            $this->activityService->log(FilmAdded::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);

            $this->addFlash('success', 'filmclub_film.flash_added');

            return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $film->getId()]);
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_plugin_filmclub_film_lookup');
        }
    }

    #[Route('/{id}/edit', name: 'app_plugin_filmclub_film_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, Request $request): Response
    {
        $film = $this->filmService->get($id);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $form = $this->createForm(FilmEditType::class, $film, [
            'data' => $film,
            'attr' => ['enctype' => 'multipart/form-data'],
        ]);
        $form->get('genresCsv')->setData(implode(', ', $film->getGenres()));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            try {
                $this->filmService->update(
                    film: $film,
                    genresCsv: $form->get('genresCsv')->getData(),
                    posterFile: $form->get('posterFile')->getData(),
                    userId: $user->getId(),
                );
                $this->addFlash('success', 'filmclub_film.flash_updated');

                return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $film->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Filmclub/film/edit.html.twig', [
            'form' => $form,
            'film' => $film,
        ]);
    }

    #[Route('/manual', name: 'app_plugin_filmclub_film_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function manual(Request $request): Response
    {
        $form = $this->createForm(FilmManualType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $film = $this->filmService->createManual(
                    title: $form->get('title')->getData(),
                    year: $form->get('year')->getData(),
                    runtime: $form->get('runtime')->getData(),
                    description: $form->get('description')->getData(),
                    genres: [],
                    userId: $user->getId(),
                );

                $this->activityService->log(FilmAdded::TYPE, $user, [
                    'film_id' => $film->getId(),
                    'film_title' => $film->getTitle(),
                ]);

                $this->addFlash('success', 'filmclub_film.flash_added');

                return $this->redirectToRoute('app_filmclub_filmlist');
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Filmclub/film/manual.html.twig', [
            'form' => $form,
        ]);
    }
}
