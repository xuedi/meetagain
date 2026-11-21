<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class SessionHostSubscriber implements EventSubscriberInterface
{
    public const string HOST_KEY = '_session_host';
    private const int BEFORE_FIREWALL = 9;

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', self::BEFORE_FIREWALL],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->attributes->getBoolean('_stateless') || !$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();
        $boundHost = $session->get(self::HOST_KEY);
        if ($boundHost === null) {
            $session->set(self::HOST_KEY, $request->getHost());

            return;
        }

        if ($boundHost !== $request->getHost()) {
            $session->invalidate();
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->getBoolean('_stateless')) {
            return;
        }

        $request->getSession()->set(self::HOST_KEY, $request->getHost());
    }
}
