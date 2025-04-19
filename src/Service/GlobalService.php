<?php declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GlobalService
{
    public function __construct(private RequestStack $requestStack, private TranslationService $translationService)
    {
    }

    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getLocale();
        }

        throw new RuntimeException('Cound not get current locale');
    }

    public function getLanguageCodes(): array
    {
        return $this->translationService->getLanguageCodes();
    }

    public function getShowCookieConsent(): bool
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        return !$session->get('consent_accepted', false);
    }

    public function getShowOsm(): bool
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        return $session->get('consent_osm', false);
    }

    public function getAlternativeLanguageCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $currentUri = $request->getRequestUri();
            $currentLocale = $request->getLocale();
            if(!str_starts_with($currentUri, '/_profiler')) {
                return $this->translationService->getAltLangList($currentLocale, $currentUri);
            }
        }

        return [];
    }
}
