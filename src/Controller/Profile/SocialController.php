<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\FriendshipService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SocialController extends AbstractController
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/profile/social', name: 'app_profile_social')]
    public function social(UserRepository $repo, string $show = 'friends'): Response
    {
        $list = $this->activityService->getUserList($this->getAuthedUser());
        return $this->render('profile/social.html.twig', [
            'followers' => $repo->getFollowers($this->getAuthedUser(), true),
            'following' => $repo->getFollowing($this->getAuthedUser(), true),
            'friends' => $repo->getFriends($this->getAuthedUser()),
            'activities' => $list,
            'user' => $this->getAuthedUser(),
            'show' => $show,
        ]);
    }

    #[Route('/profile/social/friends/', name: 'app_profile_social_friends')]
    public function socialFriends(UserRepository $repo): Response
    {
        return $this->social($repo, 'friends');
    }

    #[Route('/profile/social/toggleFollow/{id}/', name: 'app_profile_social_toggle_follow')]
    public function toggleFollow(FriendshipService $service, int $id): Response
    {
        return $service->toggleFollow($id, 'app_profile_social');
    }
}
