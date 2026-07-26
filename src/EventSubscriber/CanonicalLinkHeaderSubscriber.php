<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Seo\CanonicalUrlService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class CanonicalLinkHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CanonicalUrlService $canonicalUrlService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        // After Symfony's ResponseListener (priority 0), which is what fills in Content-Type;
        // reading it any earlier makes every normal page look like a non-HTML response.
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        $canonicalUrl = $this->canonicalUrlService->getCanonicalUrl($request);
        // Appended, not set: asset preload links live in the same header and must survive.
        $response->headers->set('Link', sprintf('<%s>; rel="canonical"', $canonicalUrl), false);
    }
}
