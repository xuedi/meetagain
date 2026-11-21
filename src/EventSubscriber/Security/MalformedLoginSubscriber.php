<?php declare(strict_types=1);

namespace App\EventSubscriber\Security;

use App\Controller\SecurityController;
use App\Enum\SecurityEventType;
use App\Service\Security\RequestIdentityResolver;
use App\Service\Security\SecurityService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

readonly class MalformedLoginSubscriber implements EventSubscriberInterface
{
    private const string USERNAME_PARAMETER = '_username';

    private const string CSRF_TOKEN_ID = 'authenticate';
    private const string CSRF_PARAMETER = '_csrf_token';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private SecurityService $securityService,
        private RequestIdentityResolver $identityResolver,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 9],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            return;
        }

        if ($request->attributes->get('_route') !== SecurityController::LOGIN_ROUTE) {
            return;
        }

        if (!is_string($request->request->all()[self::USERNAME_PARAMETER] ?? null)) {
            $event->setResponse($this->backToLogin());

            return;
        }

        if ($this->identityResolver->isCrossSite($request) || !$this->hasValidToken($request)) {
            $this->refuseWithoutThrottling($event);
        }
    }

    private function refuseWithoutThrottling(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $this->securityService->event(SecurityEventType::AccessDenied, $request, ['reason' => 'csrf']);

        if (!$this->identityResolver->isCrossSite($request) && $request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new InvalidCsrfTokenException());
        }

        $event->setResponse($this->backToLogin());
    }

    private function hasValidToken(Request $request): bool
    {
        $token = $request->request->all()[self::CSRF_PARAMETER] ?? null;

        return is_string($token) && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token));
    }

    private function backToLogin(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate(SecurityController::LOGIN_ROUTE));
    }
}
