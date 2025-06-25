<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
        if (!$user->isOsmConsent()) {
            return;
        }

        $request = $event->getRequest();

        $consent = Consent::getBySession($request->getSession());
        $consent->setOsm(ConsentType::Granted);
        $request->getSession()->set('consent', $consent);
        $consent->save($request->getSession());

        $response = $event->getResponse();
        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}
