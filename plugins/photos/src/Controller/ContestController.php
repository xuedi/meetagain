<?php declare(strict_types=1);

namespace Plugin\Photos\Controller;

use App\Controller\AbstractController;
use App\Service\Seo\BreadcrumbBuilder;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotView;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\ContestService;
use Plugin\Photos\Service\PhotoService;
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
        private readonly BallotInterface $ballots,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_plugin_photos_contest', methods: ['GET'])]
    public function index(BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        $this->denyUnlessLive();

        $viewerId = $this->isGranted('ROLE_USER') ? (int) $this->getAuthedUser()->getId() : null;

        return $this->render('@Photos/contest/index.html.twig', [
            'openContest' => $this->contestService->getOpenContest($viewerId),
            'itemType' => PhotoService::ITEM_TYPE,
            'winners' => $this->winners($viewerId),
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

    #[Route('/{id}/vote', name: 'app_plugin_photos_contest_vote', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(int $id, Request $request): Response
    {
        $this->denyUnlessLive();
        $this->denyUnlessTokenValid($request, 'app_plugin_photos_contest_vote' . $id);

        try {
            $this->ballots->cast($id, (int) $this->getAuthedUser()->getId(), $this->submittedKeys($request));
            $this->addFlash('success', 'photos_contest.flash_voted');
        } catch (Throwable) {
            $this->addFlash('error', 'photos_contest.flash_vote_refused');
        }

        return $this->redirectToRoute('app_plugin_photos_contest');
    }

    /** @return list<array{contest: BallotView, photo: Photo}> */
    private function winners(?int $viewerId): array
    {
        $winners = [];
        foreach ($this->contestService->getFinishedContests($viewerId) as $contest) {
            $photo = $contest->winningKey === null ? null : $this->photoService->get((int) $contest->winningKey);
            if ($photo instanceof Photo) {
                $winners[] = ['contest' => $contest, 'photo' => $photo];
            }
        }

        return $winners;
    }

    /** @return list<string> */
    private function submittedKeys(Request $request): array
    {
        $submitted = $request->request->all('candidates');

        return array_values(array_map(strval(...), array_filter($submitted, is_scalar(...))));
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
