<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\FollowedUser;
use App\Activity\Messages\UnFollowedUser;
use App\Entity\User;
use App\Filter\Member\MemberFilterService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

readonly class FriendshipService
{
    public function __construct(
        private UserRepository $repo,
        private BlockingService $blockingService,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private Security $security,
        private RequestStack $requestStack,
        private ActivityService $activityService,
        private MemberFilterService $memberFilterService,
    ) {}

    public function getFriends(User $user): array
    {
        return $this->repo->getFriends($user);
    }

    public function getFollowers(User $user, bool $excludeFriends = false): array
    {
        return $this->repo->getFollowers($user, $excludeFriends);
    }

    public function getFollowing(User $user, bool $excludeFriends = false): array
    {
        return $this->repo->getFollowing($user, $excludeFriends);
    }

    /**
     * @return array{following: int, followers: int, rsvp: int}
     */
    public function getSocialCounts(User $user): array
    {
        return $this->repo->getSocialCounts($user);
    }

    public function follow(User $actor, User $target): void
    {
        if ($actor->getId() === $target->getId()) {
            throw new InvalidArgumentException('Cannot follow yourself');
        }

        if ($this->blockingService->isBlocked($actor, $target)) {
            throw new DomainException('blocked');
        }

        if ($actor->getFollowing()->contains($target)) {
            return;
        }

        $actor->addFollowing($target);
        $this->em->persist($actor);
        $this->em->flush();

        $this->activityService->log(FollowedUser::TYPE, $actor, ['user_id' => $target->getId()]);
    }

    public function unfollow(User $actor, User $target): void
    {
        if (!$actor->getFollowing()->contains($target)) {
            return;
        }

        $actor->removeFollowing($target);
        $this->em->persist($actor);
        $this->em->flush();

        $this->activityService->log(UnFollowedUser::TYPE, $actor, ['user_id' => $target->getId()]);
    }

    public function toggleFollow(int $id, string $returnRoute): RedirectResponse
    {
        $currentUser = $this->getAuthedUser();
        $targetUser = $this->repo->findOneBy(['id' => $id]);
        if ($targetUser instanceof User && $currentUser->getFollowing()->contains($targetUser)) {
            $this->unfollow($currentUser, $targetUser);
        } elseif ($targetUser instanceof User && $this->mayFollow($currentUser, $targetUser)) {
            $this->follow($currentUser, $targetUser);
        }

        $route = $this->router->generate($returnRoute, [
            '_locale' => $this->requestStack->getCurrentRequest()?->getLocale(),
            'id' => $id,
        ]);

        return new RedirectResponse($route);
    }

    private function mayFollow(User $actor, User $target): bool
    {
        return !$this->blockingService->isBlocked($actor, $target) && $this->memberFilterService->isMemberAccessible((int) $target->getId());
    }

    private function getAuthedUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }
}
