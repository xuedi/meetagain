<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use App\Service\BlockingService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlockController extends AbstractController
{
    public function __construct(
        private readonly BlockingService $blockingService,
        private readonly UserRepository $userRepo,
    ) {
    }

    #[Route('/profile/block/{id}', name: 'app_profile_block_user', methods: ['POST'])]
    public function blockUser(Request $request, int $id): Response
    {
        $currentUser = $this->getAuthedUser();
        $targetUser = $this->userRepo->findOneBy(['id' => $id]);

        if ($targetUser === null) {
            throw $this->createNotFoundException();
        }

        if ($currentUser->getId() === $targetUser->getId()) {
            $this->addFlash('warning', 'You cannot block yourself.');

            return $this->redirectToRoute('app_member', [
                '_locale' => $request->getLocale(),
                'page' => 1,
            ]);
        }

        $this->blockingService->block($currentUser, $targetUser);

        $this->addFlash('success', 'User has been blocked.');

        return $this->redirectToRoute('app_member', [
            '_locale' => $request->getLocale(),
            'page' => 1,
        ]);
    }

    #[Route('/profile/unblock/{id}', name: 'app_profile_unblock_user', methods: ['POST'])]
    public function unblockUser(Request $request, int $id): Response
    {
        $currentUser = $this->getAuthedUser();
        $targetUser = $this->userRepo->findOneBy(['id' => $id]);

        if ($targetUser === null) {
            throw $this->createNotFoundException();
        }

        $this->blockingService->unblock($currentUser, $targetUser);

        $this->addFlash('success', 'User has been unblocked.');

        return $this->redirectToRoute('app_profile_blocked_users', [
            '_locale' => $request->getLocale(),
        ]);
    }

    #[Route('/profile/blocked', name: 'app_profile_blocked_users', methods: ['GET'])]
    public function blockedList(): Response
    {
        $currentUser = $this->getAuthedUser();
        $blockedUsers = $this->blockingService->getBlockedUsers($currentUser);

        return $this->render('profile/blocked.html.twig', [
            'blockedUsers' => $blockedUsers,
        ]);
    }
}
