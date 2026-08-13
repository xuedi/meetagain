<?php declare(strict_types=1);

namespace App\Security;

use App\Activity\ActivityService;
use App\Activity\Messages\Login;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\MessageRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SensitiveParameter;
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
    ) {}

    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
    }

    #[Override]
    public function checkPostAuth(UserInterface $user, #[SensitiveParameter] ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        $status = $user->getStatus();
        if ($status !== UserStatus::Active) {
            throw new CustomUserMessageAccountStatusException($this->statusMessage($status));
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $request->getSession()->set('lastLogin', $user->getLastLogin());

        $user->setLastLogin(new DateTime());
        $this->em->persist($user);
        $this->em->flush();

        $this->activityService->log(Login::TYPE, $user, []);

        if ($this->msgRepo->hasNewMessages($user)) {
            $request->getSession()->set('hasNewMessage', true);
        }
    }

    private function statusMessage(?UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Registered => 'security.account_status_registered',
            UserStatus::EmailVerified => 'security.account_status_email_verified',
            UserStatus::Blocked => 'security.account_status_blocked',
            UserStatus::Denied => 'security.account_status_denied',
            default => 'security.account_status_inactive',
        };
    }
}
