<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LanguageService $languageService,
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
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        // save explicit session, if not available restore from session
        $locale = $request->attributes->get('_locale');
        if ($locale) {
            $request->getSession()->set('_locale', $locale);
            return;
        }
        $session = $request->getSession();
        if ($session->has('_locale')) {
            $request->setLocale($session->get('_locale'));
            return;
        }

        // No explicit session locale: honour Accept-Language as a hint without
        // persisting it. Picks one of the enabled codes (q-weighted match);
        // falls back to the configured filtered default if none match.
        $codes = $this->languageService->getEnabledCodes();
        $hint = $codes === [] ? null : $request->getPreferredLanguage($codes);
        $request->setLocale($hint ?? $this->languageService->getFilteredDefaultLocale());
    }
}
