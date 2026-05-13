<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Filmclub\Activity\Messages\FilmAdded;
use Plugin\Filmclub\Activity\Messages\SuggestionCreated;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Form\FilmLookupType;
use Plugin\Filmclub\Form\FilmManualType;
use Plugin\Filmclub\Service\FilmLookupResolver;
use Plugin\Filmclub\Service\FilmService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/filmclub/film')]
final class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmService $filmService,
        private readonly FilmLookupResolver $lookupResolver,
        private readonly FilmGroupFilterService $filterService,
        private readonly ActivityService $activityService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_filmclub_filmlist', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Filmclub/film/list.html.twig', [
            'films' => $this->filmService->getApprovedList(),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_filmclub_film_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $film = $this->filmService->get($id);
        if ($film === null || !$film->isApproved()) {
            throw $this->createNotFoundException('Film not found');
        }

        return $this->render('@Filmclub/film/detail.html.twig', [
            'film' => $film,
        ]);
    }

    #[Route('/lookup', name: 'app_plugin_filmclub_film_lookup', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function lookup(Request $request): Response
    {
        $adapter = $this->lookupResolver->resolveForRequest(
            $this->filterService->getAllowedSettingsIds(),
        );

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
        $externalId = $request->request->get('externalId');
        $source = $request->request->get('source');

        if ($externalId === null || $source === null) {
            throw $this->createNotFoundException();
        }

        $adapter = $this->lookupResolver->resolveForRequest(
            $this->filterService->getAllowedSettingsIds(),
        );

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
        $isManager = $this->isGranted('ROLE_ORGANIZER');

        try {
            $film = $this->filmService->createFromMetadata($metadata, $user->getId(), $isManager);

            $activityType = $isManager ? FilmAdded::TYPE : SuggestionCreated::TYPE;
            $this->activityService->log($activityType, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);

            $flashKey = $isManager ? 'filmclub_film.flash_added' : 'filmclub_film.flash_submitted';
            $this->addFlash('success', $flashKey);

            return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $film->getId()]);
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_plugin_filmclub_film_lookup');
        }
    }

    #[Route('/manual', name: 'app_plugin_filmclub_film_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function manual(Request $request): Response
    {
        $form = $this->createForm(FilmManualType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_ORGANIZER');

            try {
                $film = $this->filmService->createManual(
                    title: $form->get('title')->getData(),
                    year: $form->get('year')->getData(),
                    runtime: $form->get('runtime')->getData(),
                    description: $form->get('description')->getData(),
                    genres: [],
                    userId: $user->getId(),
                    isManager: $isManager,
                );

                $activityType = $isManager ? FilmAdded::TYPE : SuggestionCreated::TYPE;
                $this->activityService->log($activityType, $user, [
                    'film_id' => $film->getId(),
                    'film_title' => $film->getTitle(),
                ]);

                $flashKey = $isManager ? 'filmclub_film.flash_added' : 'filmclub_film.flash_submitted';
                $this->addFlash('success', $flashKey);

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
