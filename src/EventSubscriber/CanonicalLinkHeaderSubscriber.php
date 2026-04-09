<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Seo\CanonicalUrlService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits a Link: <url>; rel="canonical" HTTP response header.
 *
 * Search engines treat this header as equivalent to <link rel="canonical"> in HTML.
 * Only added for full HTML page responses (not XHR, API, or redirect responses).
 */
final readonly class CanonicalLinkHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CanonicalUrlService $canonicalUrlService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only add to HTML responses, not redirects, API, or XHR
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        $canonicalUrl = $this->canonicalUrlService->getCanonicalUrl($request);
        $response->headers->set('Link', sprintf('<%s>; rel="canonical"', $canonicalUrl));
    }
}
