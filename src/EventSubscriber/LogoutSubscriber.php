<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Session\Consent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        // logout destroys the session, so we need to save consent again
        $consent = Consent::createByCookies($event->getRequest()->cookies);
        $consent->save($event->getRequest()->getSession());
    }
}
