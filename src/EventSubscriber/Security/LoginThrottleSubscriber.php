<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Service\Security\RateLimitLogger;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Logs every login attempt that Symfony's login throttler refuses with a
 * TooManyLoginAttemptsAuthenticationException, so it shows up in the admin
 * Rate-limiting tab alongside the form-driven rate-limit refusals.
 *
 * Why no rendered page (asymmetry vs the other two security subscribers):
 * LoginFailureEvent fires inside the firewall, before any controller. There
 * is no kernel.exception to intercept and no place for a custom rendered
 * page. The user-facing message reaches the login form via Symfony's
 * `last_authentication_error` mechanism, translated through the `security`
 * domain (`Too many failed login attempts...`).
 */
readonly class LoginThrottleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimitLogger $rateLimitLogger,
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

        $this->rateLimitLogger->log('login_throttling', $event->getRequest(), $identifier);
    }
}
