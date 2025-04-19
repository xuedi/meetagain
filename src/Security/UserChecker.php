<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserActivity;
use App\Entity\UserStatus;
use App\Service\ActivityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly ActivityService $activityService,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    )
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() !== UserStatus::Active) {
            throw new CustomUserMessageAccountStatusException('The user is not anymore or not jet active');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $this->requestStack->getCurrentRequest()->getSession()->set('lastLogin', $user->getLastLogin());

        $user->setLastLogin(new DateTime());
        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(UserActivity::Login, $user, []);
    }
}