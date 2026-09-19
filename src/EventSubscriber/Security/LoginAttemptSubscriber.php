<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Activity\ActivityService;
use App\Activity\Messages\LoginMeasuresActivated;
use App\Entity\User;
use App\Enum\SecurityEventType;
use App\Service\Security\LoginGuard;
use App\Service\Security\SecurityService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoginAttemptSubscriber implements EventSubscriberInterface
{
    public const string ROUTE_DEFAULT = '_login_attempt';
    public const string HUMAN_CHECK_FAILED = 'security.human_check_failed';
    private const int BEFORE_USER_CHECKS_PRIORITY = 300;

    public function __construct(
        private SecurityService $securityService,
        private LoginGuard $loginGuard,
        private ActivityService $activityService,
        private RequestStack $requestStack,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', self::BEFORE_USER_CHECKS_PRIORITY],
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !self::isLoginAttempt($request) || !$this->loginGuard->isActive($request)) {
            return;
        }

        if ($request->attributes->getBoolean('_stateless')) {
            throw new CustomUserMessageAuthenticationException(self::HUMAN_CHECK_FAILED);
        }

        $form = $this->loginGuard->createMeasuresForm();
        $form->submit($request->request->all(LoginGuard::FORM_NAME));
        if ($form->isValid()) {
            return;
        }

        throw new CustomUserMessageAuthenticationException(self::HUMAN_CHECK_FAILED);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            $this->reportThrottle($event);

            return;
        }

        $request = $event->getRequest();
        if (!self::isLoginAttempt($request)) {
            return;
        }

        if (!$this->loginGuard->recordFailure($request) || !$this->loginGuard->mayAnnounceActivation()) {
            return;
        }

        $this->activityService->log(LoginMeasuresActivated::TYPE, $this->resolveUser($event->getPassport()), [
            'ip' => $request->getClientIp() ?? '',
            'attempts' => $this->loginGuard->threshold($request),
        ]);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->loginGuard->reset($event->getRequest());
    }

    private static function isLoginAttempt(Request $request): bool
    {
        return $request->attributes->getBoolean(self::ROUTE_DEFAULT);
    }

    private function reportThrottle(LoginFailureEvent $event): void
    {
        $context = ['limiter' => 'login_throttling'];
        $identifier = $this->userBadge($event->getPassport())?->getUserIdentifier();
        if ($identifier !== null && $identifier !== '') {
            $context['userIdentifier'] = $identifier;
        }

        $this->securityService->event(SecurityEventType::RateLimit, $event->getRequest(), $context);
    }

    private function resolveUser(?Passport $passport): ?User
    {
        try {
            $user = $this->userBadge($passport)?->getUser();
        } catch (AuthenticationException) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    private function userBadge(?Passport $passport): ?UserBadge
    {
        if ($passport === null || !$passport->hasBadge(UserBadge::class)) {
            return null;
        }

        $badge = $passport->getBadge(UserBadge::class);

        return $badge instanceof UserBadge ? $badge : null;
    }
}
