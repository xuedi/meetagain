<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
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
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function toggleFollow(int $id, string $returnRoute): RedirectResponse
    {
        $currentUser = $this->getAuthedUser();
        $targetUser = $this->repo->findOneBy(['id' => $id]);

        if ($currentUser->getFollowing()->contains($targetUser)) {
            $currentUser->removeFollowing($targetUser);
        } else {
            $currentUser->addFollowing($targetUser);
        }

        $this->em->persist($currentUser);
        $this->em->flush();

        $route = $this->router->generate($returnRoute, [
            '_locale' => $this->requestStack->getCurrentRequest()?->getLocale(),
            'id' => $targetUser->getId()
        ]);

        return new RedirectResponse($route);
    }

    private function getAuthedUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                "Should never happen, see: config/packages/security.yaml"
            );
        }

        return $user;
    }
}
