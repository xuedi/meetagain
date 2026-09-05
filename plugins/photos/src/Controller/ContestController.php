<?php declare(strict_types=1);

namespace Plugin\Photos\Controller;

use App\Controller\AbstractController;
use App\Service\Seo\BreadcrumbBuilder;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;
use Plugin\Voting\Entity\Poll;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route('/photos/contest')]
final class ContestController extends AbstractController
{
    public function __construct(
        private readonly ContestService $contestService,
        private readonly PhotoService $photoService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_plugin_photos_contest', methods: ['GET'])]
    public function index(BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        $this->denyUnlessLive();

        return $this->render('@Photos/contest/index.html.twig', [
            'openContest' => $this->contestService->getOpenContest(),
            'winners' => $this->winners(),
            'queued' => count($this->contestService->getQueuedIds()),
            'canStart' => $this->isGranted('ROLE_STEWARD'),
            'breadcrumbs' => $breadcrumbBuilder->build('app_photos_photolist', 'photos.menu_main', $this->translator->trans('photos_contest.page_title')),
        ]);
    }

    #[Route('/start', name: 'app_plugin_photos_contest_start', methods: ['POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function start(Request $request): Response
    {
        $this->denyUnlessLive();
        $this->denyUnlessTokenValid($request, 'app_plugin_photos_contest_start');

        try {
            $this->contestService->start((int) $this->getAuthedUser()->getId());
            $this->addFlash('success', 'photos_contest.flash_started');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_photos_contest');
    }

    #[Route('/{id}/submit', name: 'app_plugin_photos_contest_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(int $id, Request $request): Response
    {
        $photo = $this->ownPhoto($id, $request, 'app_plugin_photos_contest_submit');

        try {
            $this->contestService->submit($photo);
            $this->addFlash('success', 'photos_contest.flash_submitted');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_plugin_photos_photo_show', ['id' => $id]);
    }

    #[Route('/{id}/withdraw', name: 'app_plugin_photos_contest_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdraw(int $id, Request $request): Response
    {
        $this->contestService->withdraw($this->ownPhoto($id, $request, 'app_plugin_photos_contest_withdraw'));
        $this->addFlash('success', 'photos_contest.flash_withdrawn');

        return $this->redirectToRoute('app_plugin_photos_photo_show', ['id' => $id]);
    }

    /** @return list<array{poll: Poll, photo: Photo}> */
    private function winners(): array
    {
        $winners = [];
        foreach ($this->contestService->getFinishedContests() as $poll) {
            $winningId = $poll->getWinningItemId();
            $photo = $winningId === null ? null : $this->photoService->get($winningId);
            if ($photo instanceof Photo) {
                $winners[] = ['poll' => $poll, 'photo' => $photo];
            }
        }

        return $winners;
    }

    private function ownPhoto(int $id, Request $request, string $tokenId): Photo
    {
        $this->denyUnlessLive();
        $this->denyUnlessTokenValid($request, $tokenId . $id);

        $photo = $this->photoService->getManaged($id);
        if ($photo === null) {
            throw $this->createNotFoundException('Photo not found');
        }

        if (!$this->photoService->isOwnedBy($photo, $this->getAuthedUser())) {
            throw $this->createAccessDeniedException();
        }

        return $photo;
    }

    private function denyUnlessLive(): void
    {
        if (!$this->contestService->isLive()) {
            throw $this->createNotFoundException();
        }
    }

    private function denyUnlessTokenValid(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }
}
