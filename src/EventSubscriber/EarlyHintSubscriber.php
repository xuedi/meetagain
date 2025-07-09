<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EarlyHintSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->isMethod('GET')) {
            return;
        }

        $hints = [
            '</stylesheet/bulma.min.css>; rel=preload; as=style',
            '</stylesheet/fontawesome.min.css>; rel=preload; as=style',
            '</stylesheet/fontawesome-solid.css>; rel=preload; as=style',
            '</stylesheet/fonts.css>; rel=preload; as=style',
            '</stylesheet/custom.css>; rel=preload; as=style',
            '</javascript/custom.js>; rel=preload; as=script',
            '</fonts/fa-solid-900.woff2>; rel=preload; as=font',
        ];

        // Create and send the 103 Early Hints response
        $earlyHintsResponse = new Response('', 103, ['Link' => implode(', ', $hints)]);
        $request->attributes->set('earlyHints', $earlyHintsResponse);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($earlyHintsResponse = $request->attributes->get('earlyHints')) {
            $event->getResponse()->headers->set('Link', $earlyHintsResponse->headers->get('Link'));
        }
    }
}
