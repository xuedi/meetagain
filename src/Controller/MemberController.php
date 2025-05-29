<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\FriendshipService;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class MemberController extends AbstractController
{
    private const int PAGE_SIZE = 24;

    #[Route('/members/{page}', name: 'app_member')]
    public function index(UserRepository $repo, int $page = 1): Response
    {
        $offset = ($page - 1) * self::PAGE_SIZE;
        $userTotal = $repo->getNumberOfActivePublicMembers();
        $users = $repo->findActivePublicMembers(self::PAGE_SIZE, $offset);
        return $this->render('member/index.html.twig', [
            'users' => $users,
            'userTotal' => $userTotal,
            'pageSize' => self::PAGE_SIZE,
            'pageCurrent' => $page,
            'pageTotal' => ceil($userTotal / self::PAGE_SIZE),
        ]);
    }

    #[Route('/members/view/{id}', name: 'app_member_view')]
    public function view(UserRepository $repo, int $id): Response
    {
        try {
            $currentUser = $this->getAuthedUser();
            $userDetails = $repo->findOneBy(['id' => $id]);

            return $this->render('member/view.html.twig', [
                'currentUser' => $currentUser,
                'userDetails' => $userDetails,
                'isFollow' => $currentUser->getFollowing()->contains($userDetails),
            ]);
        } catch (AuthenticationCredentialsNotFoundException) {
            return $this->render('member/403.html.twig');
        }
    }

    #[Route('/members/toggleFollow/{id}', name: 'app_member_toggle_follow')]
    public function toggleFollow(FriendshipService $service, int $id): Response
    {
        return $service->toggleFollow($id, 'app_member_view');
    }

    #[Route('/members/rotate-avatar/{id}', name: 'app_member_rotate_avatar')]
    public function rotateProfile(UserRepository $repo, ImageService $imageService, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $user = $repo->findOneBy(['id' => $id]);
        dump($user);
        if ($user->getImage() !== null) {
            $imageService->rotateThumbNail($user->getImage());
        }

        return $this->redirectToRoute('app_member_view', ['id' => $id]);
    }
}
