<?php declare(strict_types=1);

namespace Plugin\Films\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Films\Activity\Messages\FilmAdded;
use Plugin\Films\Entity\Film;
use Plugin\Films\Form\FilmEditType;
use Plugin\Films\Form\FilmLookupType;
use Plugin\Films\Form\FilmManualType;
use Plugin\Films\Service\FilmLookupResolver;
use Plugin\Films\Service\FilmService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/films')]
final class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmService $filmService,
        private readonly FilmLookupResolver $lookupResolver,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('', name: 'app_films_filmlist', methods: ['GET'])]
    public function list(): Response
    {
        $itemIds = array_map(static fn(Film $film): int => (int) $film->getId(), $this->filmService->getList());

        return $this->render('@Films/film/list.html.twig', [
            'itemIds' => $itemIds,
        ]);
    }

    #[Route('/lookup', name: 'app_plugin_films_film_lookup', methods: ['GET'])]
    #[IsGranted('ROLE_STEWARD')]
    public function lookup(Request $request): Response
    {
        $adapter = $this->lookupResolver->resolve();

        if ($adapter === null) {
            $this->addFlash('info', 'films_film.flash_no_adapter');

            return $this->redirectToRoute('app_plugin_films_film_manual');
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
                $this->addFlash('error', 'films_film.flash_lookup_error');
            }
        }

        return $this->render('@Films/film/lookup.html.twig', [
            'form' => $form,
            'results' => $results,
        ]);
    }

    #[Route('/import', name: 'app_plugin_films_film_import', methods: ['POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function import(Request $request): Response
    {
        $externalId = (string) $request->request->get('externalId', '');
        $source = (string) $request->request->get('source', '');

        if ($externalId === '' || $source === '') {
            throw $this->createNotFoundException();
        }

        $adapter = $this->lookupResolver->resolve();

        if ($adapter === null) {
            $this->addFlash('error', 'films_film.flash_no_adapter');

            return $this->redirectToRoute('app_plugin_films_film_manual');
        }

        $metadata = $adapter->fetchById($externalId, $request->getLocale());
        if ($metadata === null) {
            $this->addFlash('error', 'films_film.flash_not_found');

            return $this->redirectToRoute('app_plugin_films_film_lookup');
        }

        $user = $this->getAuthedUser();

        try {
            $film = $this->filmService->createFromMetadata($metadata, $user->getId());

            $this->activityService->log(FilmAdded::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);

            $this->addFlash('success', 'films_film.flash_added');

            return $this->redirectToRoute('app_plugin_films_film_show', ['id' => $film->getId()]);
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_plugin_films_film_lookup');
        }
    }

    #[Route('/manual', name: 'app_plugin_films_film_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
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

                $this->addFlash('success', 'films_film.flash_added');

                return $this->redirectToRoute('app_films_filmlist');
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Films/film/manual.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_films_film_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $film = $this->filmService->get($id);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        return $this->render('@Films/film/detail.html.twig', [
            'film' => $film,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_films_film_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
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
                $this->addFlash('success', 'films_film.flash_updated');

                return $this->redirectToRoute('app_plugin_films_film_show', ['id' => $film->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Films/film/edit.html.twig', [
            'form' => $form,
            'film' => $film,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_films_film_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
    public function delete(int $id, Request $request): Response
    {
        $film = $this->filmService->get($id);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        if (!$this->isCsrfTokenValid('app_plugin_films_film_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->filmService->delete($film);
        $this->addFlash('success', 'films_film.flash_deleted');

        return $this->redirectToRoute('app_films_filmlist');
    }
}
