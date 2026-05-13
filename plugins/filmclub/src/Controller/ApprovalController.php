<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Filmclub\Activity\Messages\FilmApproved;
use Plugin\Filmclub\Activity\Messages\FilmRejected;
use Plugin\Filmclub\Service\FilmService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/manage')]
#[IsGranted('ROLE_ORGANIZER')]
final class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly FilmService $filmService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/pending', name: 'app_plugin_filmclub_manage_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Filmclub/manage/pending.html.twig', [
            'films' => $this->filmService->getPendingList(),
        ]);
    }

    #[Route('/approve/{filmId}', name: 'app_plugin_filmclub_manage_approve', methods: ['POST'])]
    public function approve(int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException();
        }

        try {
            $this->filmService->approve($filmId);
            $this->activityService->log(FilmApproved::TYPE, $this->getAuthedUser(), [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);
            $this->addFlash('success', 'filmclub_manage.flash_approved');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_manage_pending');
    }

    #[Route('/reject/{filmId}', name: 'app_plugin_filmclub_manage_reject', methods: ['POST'])]
    public function reject(int $filmId): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException();
        }

        $title = $film->getTitle();

        try {
            $this->filmService->reject($filmId);
            $this->activityService->log(FilmRejected::TYPE, $this->getAuthedUser(), [
                'film_title' => $title,
            ]);
            $this->addFlash('success', 'filmclub_manage.flash_rejected');
        } catch (RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_filmclub_manage_pending');
    }
}
