<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\FollowedUser;
use App\Activity\Messages\UnFollowedUser;
use App\Entity\User;
use App\Repository\UserBlockRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

// TODO: all the controller access tools are a bit excessive
readonly class FriendshipService
{
    public function __construct(
        private UserRepository $repo,
        private UserBlockRepository $blockRepo,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private Security $security,
        private RequestStack $requestStack,
        private ActivityService $activityService,
    ) {}

    public function toggleFollow(int $id, string $returnRoute): RedirectResponse
    {
        $currentUser = $this->getAuthedUser();
        $targetUser = $this->repo->findOneBy(['id' => $id]);

        // Check if either user has blocked the other
        if ($this->blockRepo->isBlockedEitherWay($currentUser, $targetUser)) {
            // Cannot follow if blocked - just redirect back
            $route = $this->router->generate($returnRoute, [
                '_locale' => $this->requestStack->getCurrentRequest()?->getLocale(),
                'id' => $targetUser->getId(),
            ]);

            return new RedirectResponse($route);
        }

        $isFollowing = $currentUser->getFollowing()->contains($targetUser);
        if ($isFollowing) {
            $currentUser->removeFollowing($targetUser);
            $activityType = UnFollowedUser::TYPE;
        } else {
            $currentUser->addFollowing($targetUser);
            $activityType = FollowedUser::TYPE;
        }

        $this->em->persist($currentUser);
        $this->em->flush();

        $this->activityService->log($activityType, $currentUser, ['user_id' => $targetUser->getId()]);

        $route = $this->router->generate($returnRoute, [
            '_locale' => $this->requestStack->getCurrentRequest()?->getLocale(),
            'id' => $targetUser->getId(),
        ]);

        return new RedirectResponse($route);
    }

    private function getAuthedUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                'Should never happen, see: config/packages/security.yaml',
            );
        }

        return $user;
    }
}
