<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Filmclub\Activity\Messages\WishlistAdded;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\WishlistService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/wishlist')]
#[IsGranted('ROLE_USER')]
final class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly FilmService $filmService,
        private readonly ActivityService $activityService,
        private readonly EventRepository $eventRepo,
        private readonly FilmGroupFilterService $groupFilter,
    ) {}

    #[Route('/mine', name: 'app_plugin_filmclub_wishlist_mine', methods: ['GET'])]
    public function mine(): Response
    {
        $user = $this->getAuthedUser();
        $entries = $this->wishlistService->listForUser($user->getId());

        $waitingSince = [];
        foreach ($entries as $entry) {
            if ($entry->getCreatedAt() !== null) {
                $waitingSince[$entry->getId()] = $this->wishlistService->countPastEventsInGroupSince(
                    $entry->getCreatedAt(),
                );
            } else {
                $waitingSince[$entry->getId()] = 0;
            }
        }

        return $this->render('@Filmclub/wishlist/mine.html.twig', [
            'entries' => $entries,
            'waitingSince' => $waitingSince,
        ]);
    }

    #[Route('/group', name: 'app_plugin_filmclub_wishlist_group', methods: ['GET'])]
    public function group(): Response
    {
        $upcomingEvents = $this->eventRepo->getUpcomingEvents(10, $this->groupFilter->getAllowedEventIds());

        return $this->render('@Filmclub/wishlist/group.html.twig', [
            'byFilm' => $this->wishlistService->aggregateByFilm(),
            'byMember' => $this->wishlistService->groupByMember(),
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    #[Route('/add/{filmId}', name: 'app_plugin_filmclub_wishlist_add', methods: ['POST'])]
    public function add(int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();
        $this->wishlistService->add($film, $user->getId());
        $this->activityService->log(WishlistAdded::TYPE, $user, [
            'film_id' => $film->getId(),
            'film_title' => $film->getTitle(),
        ]);
        $this->addFlash('success', 'filmclub_wishlist.flash_added');

        return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $filmId]);
    }

    #[Route('/remove/{filmId}', name: 'app_plugin_filmclub_wishlist_remove', methods: ['POST'])]
    public function remove(int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();
        $this->wishlistService->remove($film, $user->getId());
        $this->addFlash('success', 'filmclub_wishlist.flash_removed');

        return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $filmId]);
    }
}
