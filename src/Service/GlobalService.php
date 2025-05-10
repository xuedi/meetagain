<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GlobalService
{
    public function __construct(private RequestStack $requestStack, private TranslationService $translationService)
    {
    }

    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
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
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if ($session === null) {
            return true;
        }

        if (Consent::getBySession($session)->getCookies() === ConsentType::Unknown) {
            return true;
        }

        return false;
    }

    public function getShowOsm(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if ($session === null) {
            return false;
        }

        if (Consent::getBySession($session)->getOsm() === ConsentType::Granted) {
            return true;
        }

        return false;
    }

    public function getAlternativeLanguageCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $currentUri = $request->getRequestUri();
            $currentLocale = $request->getLocale();
            if (!str_starts_with($currentUri, '/_profiler')) {
                return $this->translationService->getAltLangList($currentLocale, $currentUri);
            }
        }

        return [];
    }
}
