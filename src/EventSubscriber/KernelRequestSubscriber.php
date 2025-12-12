<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\SecurityController;
use App\Entity\Session\Consent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class KernelRequestSubscriber implements EventSubscriberInterface
{
    #[\Override]
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
        $request = $event->getRequest();

        // clean temp redirect route if not showing login controller
        $currentRoute = $request->attributes->get('_route');
        if (!in_array($currentRoute, [null, '_wdt', SecurityController::LOGIN_ROUTE])) {
            $request->getSession()->remove('redirectUrl');
        }

        // setting cookie consent session from cookie
        $consentSession = $request->getSession()->get('consent_accepted');
        if ($consentSession === null) {
            $consent = Consent::createByCookies($request->cookies);
            $request->getSession()->set('consent', $consent);
        }
    }
}
