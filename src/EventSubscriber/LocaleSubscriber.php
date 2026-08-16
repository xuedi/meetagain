<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Config\LanguageService;
use App\Service\Config\LocaleCookieService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LanguageService $languageService,
        private LocaleCookieService $localeCookieService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                // Run after SessionListener (128) so the session factory is
                // attached, and after RouterListener (32) so the `_locale`
                // route attribute is populated. Stays above LocaleListener
                // (16) so the translator picks up the session-based locale.
                ['onKernelRequest', 20],
            ],
            KernelEvents::RESPONSE => [
                ['onKernelResponse', 0],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // The URL locale is the explicit choice and beats every stored preference.
        $locale = $request->attributes->get('_locale');
        if ($locale) {
            if ($request->hasPreviousSession()) {
                $request->getSession()->set('_locale', $locale);
            }

            return;
        }

        // Session reads need a started session; the cookie fallback below must stay reachable without one.
        if ($request->hasPreviousSession()) {
            $session = $request->getSession();
            if ($session->has('_locale')) {
                $request->setLocale($session->get('_locale'));

                return;
            }
        }

        // The consented cookie outranks Accept-Language, which below stays a per-request hint only.
        $cookieLocale = $this->localeCookieService->getValidLocale($request);
        if ($cookieLocale !== null) {
            $request->setLocale($cookieLocale);

            return;
        }

        $codes = $this->languageService->getEnabledCodes();
        $hint = $codes === [] ? null : $request->getPreferredLanguage($codes);
        $request->setLocale($hint ?? $this->languageService->getFilteredDefaultLocale());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $locale = $event->getRequest()->attributes->get('_locale');
        if (!is_string($locale)) {
            return;
        }

        $this->localeCookieService->attachIfConsentGranted($event->getRequest(), $event->getResponse(), $locale);
    }
}
