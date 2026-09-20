<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Config\LocaleCookieService;
use App\Service\Member\ConsentService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocaleCookieService $localeCookieService,
        private ConsentService $consentService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        if (!$user instanceof User || $request->attributes->getBoolean('_stateless')) {
            return;
        }
        $session = $request->getSession();
        $session->set('_locale', $user->getLocale());

        $response = $event->getResponse();
        if ($response !== null && $this->localeCookieService->isConsentGranted($request)) {
            $response->headers->setCookie($this->localeCookieService->createCookie($user->getLocale()));
        }

        $this->consentService->setShowOsm((bool) $user->isOsmConsent(), $response);
    }
}
