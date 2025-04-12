<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class GlobalService
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

        return [];
    }

    public function getLanguageCodes(): array
    {
        return $this->translationService->getLanguageCodes();
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
