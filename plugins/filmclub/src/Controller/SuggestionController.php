<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Filmclub\Activity\Messages\SuggestionCreated;
use Plugin\Filmclub\Activity\Messages\SuggestionWithdrawn;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\SuggestionService;
use Plugin\Filmclub\Service\WishlistService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub')]
#[IsGranted('ROLE_USER')]
final class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly SuggestionService $suggestionService,
        private readonly FilmService $filmService,
        private readonly WishlistService $wishlistService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/suggestions', name: 'app_plugin_filmclub_suggestion_list', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getAuthedUser();

        $userSuggestions = $this->suggestionService->getUserPendingSuggestions($user->getId());
        $suggestedFilmIds = array_map(static fn($s) => $s->getFilm()->getId(), $userSuggestions);
        $userWishlistEntries = $this->wishlistService->listForUser($user->getId());
        $wishlistFilmIds = array_map(static fn($e) => $e->getFilm()->getId(), $userWishlistEntries);

        return $this->render('@Filmclub/suggestion/list.html.twig', [
            'suggestions' => $this->suggestionService->getPendingSuggestions(),
            'userSuggestions' => $userSuggestions,
            'suggestedFilmIds' => $suggestedFilmIds,
            'wishlistFilmIds' => $wishlistFilmIds,
            'approvedFilms' => array_filter(
                $this->filmService->getApprovedList(),
                static fn($f) => !in_array($f->getId(), $suggestedFilmIds, true),
            ),
        ]);
    }

    #[Route('/suggest/{filmId}', name: 'app_plugin_filmclub_suggest', methods: ['POST'])]
    public function suggest(int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null || !$film->isApproved()) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();

        try {
            $this->suggestionService->suggest($film, $user->getId());
            $this->activityService->log(SuggestionCreated::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);
            $this->addFlash('success', 'filmclub_suggestion.flash_suggested');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_suggestion_list');
    }

    #[Route('/withdraw/{suggestionId}', name: 'app_plugin_filmclub_suggestion_withdraw', methods: ['POST'])]
    public function withdraw(int $suggestionId): Response
    {
        $user = $this->getAuthedUser();
        $suggestion = $this->suggestionService->get($suggestionId);

        try {
            $this->suggestionService->withdraw($suggestionId, $user->getId());
            if ($suggestion !== null) {
                $this->activityService->log(SuggestionWithdrawn::TYPE, $user, [
                    'film_title' => $suggestion->getFilm()->getTitle(),
                ]);
            }
            $this->addFlash('success', 'filmclub_suggestion.flash_withdrawn');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_suggestion_list');
    }
}
