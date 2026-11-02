<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleValidationSubscriber implements EventSubscriberInterface
{
    public const array RENAMED_LOCALES = ['cn' => 'zh'];

    public function __construct(
        private LanguageService $languageService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 24], // After RouterListener (32) sets `_locale`, before LocaleSubscriber (20) stores it
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // sub-requests inherit the locale the main request already validated
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->attributes->get('_locale') ?? $request->attributes->get('locale');

        // a route without a locale prefix has nothing to validate
        if ($locale === null) {
            return;
        }

        // a renamed code is gone for good, so crawlers get a permanent redirect to its successor
        if (isset(self::RENAMED_LOCALES[$locale])) {
            $event->setResponse(new RedirectResponse($this->swapLocale($request, $locale, self::RENAMED_LOCALES[$locale]), 301));

            return;
        }

        // not enabled anywhere: two letters that are no language answer 404, before the translator builds a catalogue
        if (!$this->languageService->isValidCode($locale)) {
            if ($this->languageService->findByCode($locale) === null) {
                throw new NotFoundHttpException(sprintf('Locale "%s" is not available', $locale));
            }

            // a language switched off can return, so its stale URLs get a temporary redirect, not a 404
            $event->setResponse(new RedirectResponse($this->swapLocale($request, $locale, $this->languageService->getFilteredDefaultLocale()), 302));

            return;
        }

        // enabled, but not offered on this host: send the visitor to the host's default locale
        if (!$this->languageService->isFilteredValidCode($locale)) {
            $event->setResponse(new RedirectResponse($this->swapLocale($request, $locale, $this->languageService->getFilteredDefaultLocale()), 302));
        }
    }

    private function swapLocale(Request $request, string $from, string $to): string
    {
        return (string) preg_replace('#^/' . preg_quote($from, '#') . '(/|$)#', '/' . $to . '$1', $request->getRequestUri());
    }
}
