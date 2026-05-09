<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Enum\SecurityEventType;
use App\Service\Security\SecurityService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Logs every login attempt that Symfony's login throttler refuses with a
 * TooManyLoginAttemptsAuthenticationException, so it shows up in the admin
 * Rate-limiting tab alongside the form-driven rate-limit refusals.
 */
readonly class LoginThrottleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SecurityService $securityService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!$event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $identifier = null;
        $passport = $event->getPassport();
        if ($passport !== null && $passport->hasBadge(UserBadge::class)) {
            $badge = $passport->getBadge(UserBadge::class);
            if ($badge instanceof UserBadge) {
                $identifier = $badge->getUserIdentifier();
            }
        }

        $context = ['limiter' => 'login_throttling'];
        if ($identifier !== null && $identifier !== '') {
            $context['userIdentifier'] = $identifier;
        }

        $this->securityService->event(SecurityEventType::RateLimit, $event->getRequest(), $context);
    }
}
