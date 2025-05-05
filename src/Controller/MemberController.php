<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserStatus;
use App\Repository\UserRepository;
use App\Service\FriendshipService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

#[Route('/members')]
class MemberController extends AbstractController
{
    #[Route('', name: 'app_member')]
    public function index(UserRepository $repo): Response
    {
        return $this->render('member/index.html.twig', [
            'members' => $repo->findBy(['status' => UserStatus::Active, 'public' => true], ['createdAt' => 'ASC']),
        ]);
    }

    #[Route('/{id}', name: 'app_member_view')]
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

    #[Route('/toggleFollow/{id}', name: 'app_member_toggle_follow')]
    public function toggleFollow(FriendshipService $service, int $id): Response
    {
        return $service->toggleFollow($id, 'app_member_view');
    }
}
