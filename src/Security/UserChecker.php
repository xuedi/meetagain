<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Entity\UserStatus;
use App\Repository\MessageRepository;
use App\Service\ActivityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private ActivityService $activityService,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private MessageRepository $msgRepo,
    ) {
    }

    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if (!($user instanceof User)) {
            return;
        }

        if ($user->getStatus() !== UserStatus::Active) {
            throw new CustomUserMessageAccountStatusException('The user is not anymore or not jet active');
        }
    }

    #[Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!($user instanceof User)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!($request instanceof Request)) {
            return;
        }

        $request->getSession()->set('lastLogin', $user->getLastLogin());

        $user->setLastLogin(new DateTime());
        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(ActivityType::Login, $user, []);

        if ($this->msgRepo->hasNewMessages($user)) {
            $request->getSession()->set('hasNewMessage', true);
        }
    }
}
