<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Cheap insurance against API abuse: per-IP global limiter applied to every
 * /api/* request before the firewall runs. Generous ceiling (600 / 5min);
 * normal traffic never trips it. Endpoint-specific limiters (e.g. the
 * tighter `api_token` limiter on /api/auth/token) layer on top.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 250)]
readonly class ApiRateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.api_global')]
        private RateLimiterFactory $apiGlobalLimiter,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $limiter = $this->apiGlobalLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException(max(1, $retryAfter), 'API rate limit exceeded');
        }

        $response = $event->getResponse();
        if ($response instanceof Response) {
            return;
        }
    }
}
