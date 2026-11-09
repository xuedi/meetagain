<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Config\LocaleCookieService;
use App\Service\Member\ConsentService;
use App\Service\Support\ThreadService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

readonly class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocaleCookieService $localeCookieService,
        private ConsentService $consentService,
        private ThreadService $threadService,
        private Security $security,
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
        $this->forgetOwnThreadViews($session);

        $response = $event->getResponse();
        if ($response !== null && $this->localeCookieService->isConsentGranted($request)) {
            $response->headers->setCookie($this->localeCookieService->createCookie($user->getLocale()));
        }

        $this->consentService->setShowOsm((bool) $user->isOsmConsent(), $response);
    }

    private function forgetOwnThreadViews(SessionInterface $session): void
    {
        $viewIds = $session->get(ThreadService::SESSION_VIEWS_KEY);
        if (!is_array($viewIds) || !$this->security->isGranted('ROLE_STEWARD')) {
            return;
        }

        $this->threadService->forgetViews(array_values(array_filter($viewIds, is_int(...))));
        $session->remove(ThreadService::SESSION_VIEWS_KEY);
    }
}
