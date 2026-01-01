<?php declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LoginAttemptService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[AsEventListener(event: LoginFailureEvent::class)]
readonly class LoginFailureListener
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private UserRepository $userRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $passport = $event->getPassport();
        if (!$passport instanceof Passport) {
            return;
        }

        $email = $passport->getBadge(UserBadge::class)?->getUserIdentifier();
        if ($email === null) {
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return;
        }

        $this->loginAttemptService->log(
            $user,
            $request->getClientIp() ?? 'unknown',
            false,
            $request->headers->get('User-Agent')
        );
    }
}
