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
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly \App\Repository\UserRepository $repo,
        private readonly \App\Service\FriendshipService $service,
    ) {
    }

    #[Route('/profile/social', name: 'app_profile_social')]
    public function social(string $show = 'friends'): Response
    {
        return $this->render('profile/social.html.twig', [
            'followers' => $this->repo->getFollowers($this->getAuthedUser(), true),
            'following' => $this->repo->getFollowing($this->getAuthedUser(), true),
            'friends' => $this->repo->getFriends($this->getAuthedUser()),
            'activities' => $this->activityService->getUserList($this->getAuthedUser()),
            'user' => $this->getAuthedUser(),
            'show' => $show,
        ]);
    }

    #[Route('/profile/social/friends/', name: 'app_profile_social_friends')]
    public function socialFriends(): Response
    {
        return $this->social($this->repo, 'friends');
    }

    #[Route('/profile/social/toggleFollow/{id}/', name: 'app_profile_social_toggle_follow')]
    public function toggleFollow(int $id): Response
    {
        return $this->service->toggleFollow($id, 'app_profile_social');
    }
}
