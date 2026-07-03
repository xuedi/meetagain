<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\SecurityController;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
        if ($request->getMethod() === 'GET' && $isJumpBackEligible) {
            $request->getSession()->set('redirectUrl', $request->getRequestUri());
        }
    }

    private function isJumpBackEligible(mixed $route, string $path): bool
    {
        if (!is_string($route) || $route === '') {
            return false;
        }

        if (in_array($route, self::JUMPBACK_SKIP_ROUTES, true)) {
            return false;
        }

        $matchesSkippedPrefix = array_any(
            self::JUMPBACK_SKIP_PATH_PREFIXES,
            static fn(string $prefix): bool => str_starts_with($path, $prefix),
        );

        return !$matchesSkippedPrefix;
    }
}
