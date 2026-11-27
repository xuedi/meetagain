<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\SecurityController;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

readonly class KernelRequestSubscriber implements EventSubscriberInterface
{
    private const array JUMPBACK_SKIP_ROUTES = [
        SecurityController::LOGIN_ROUTE,
        'app_security_logout',
        'app_security_blocked',
        'app_register',
        'app_register_confirm_email',
        'app_reset',
        'app_reset_password',
        'app_jump_landing',
        'app_jump_forwarder',
    ];

    private const array JUMPBACK_SKIP_PATH_PREFIXES = [
        '/api/',
        '/ajax/',
        '/jump/',
        '/_',
    ];

    public function __construct(
        private UrlMatcherInterface $urlMatcher,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 10],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $isJumpBackEligible = $this->isJumpBackEligible($request->attributes->get('_route'), $request->getPathInfo());
        if ($request->getMethod() === 'GET' && $request->hasPreviousSession() && $isJumpBackEligible && $this->isPageNavigation($request)) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }
    }

    private function isPageNavigation(Request $request): bool
    {
        $fetchDestination = $request->headers->get('Sec-Fetch-Dest');
        if ($fetchDestination !== null) {
            return $fetchDestination === 'document';
        }

        return str_contains((string) $request->headers->get('Accept', ''), 'text/html');
    }

    public function isJumpBackPath(string $uri): bool
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        try {
            $route = $this->urlMatcher->match($path)['_route'] ?? null;
        } catch (RoutingException) {
            return false;
        }

        return $this->isJumpBackEligible($route, $path);
    }

    private function isJumpBackEligible(mixed $route, string $path): bool
    {
        if (!is_string($route) || $route === '') {
            return false;
        }

        if (in_array($route, self::JUMPBACK_SKIP_ROUTES, true)) {
            return false;
        }

        $matchesSkippedPrefix = array_any(self::JUMPBACK_SKIP_PATH_PREFIXES, static fn(string $prefix): bool => str_starts_with($path, $prefix));

        return !$matchesSkippedPrefix;
    }
}
