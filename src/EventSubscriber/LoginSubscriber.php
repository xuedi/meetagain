<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Session\Consent;
use App\Entity\User;
use App\Enum\ConsentType;
use App\Service\Config\LocaleCookieService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocaleCookieService $localeCookieService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        $session = $request->getSession();
        if (!$user instanceof User) {
            return;
        }
        $session->set('_locale', $user->getLocale());

        $response = $event->getResponse();
        if ($response !== null && $this->localeCookieService->isConsentGranted($request)) {
            $response->headers->setCookie($this->localeCookieService->createCookie($user->getLocale()));
        }

        if (!$user->isOsmConsent()) {
            return;
        }

        $consent = Consent::getBySession($session);
        $consent->setOsm(ConsentType::Granted);
        $session->set('consent', $consent);
        $consent->save($request->getSession());

        if ($response === null) {
            return;
        }

        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}
