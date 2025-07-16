<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSubscriber implements EventSubscriberInterface
{
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
        if (!$user->isOsmConsent()) {
            return;
        }

        $consent = Consent::getBySession($session);
        $consent->setOsm(ConsentType::Granted);
        $session->set('consent', $consent);
        $consent->save($request->getSession());

        $response = $event->getResponse();
        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}
