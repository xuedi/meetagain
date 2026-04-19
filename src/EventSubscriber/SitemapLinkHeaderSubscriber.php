<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds `Link: </sitemap.xml>; rel="sitemap"` to public HTML responses so AI
 * agents and crawlers can discover the sitemap via HTTP headers in addition
 * to robots.txt.
 *
 * Skipped on: admin paths, sub-requests, non-200 responses, non-HTML content.
 */
final readonly class SitemapLinkHeaderSubscriber implements EventSubscriberInterface
{
    private const string SITEMAP_LINK = '</sitemap.xml>; rel="sitemap"';

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [['onKernelResponse', -10]],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/admin')) {
            return;
        }

        $existing = (string) $response->headers->get('Link', '');
        $merged = $existing === ''
            ? self::SITEMAP_LINK
            : $existing . ', ' . self::SITEMAP_LINK;

        $response->headers->set('Link', $merged);
    }
}
