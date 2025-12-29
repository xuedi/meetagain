<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\LanguageService;
use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class LanguageExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly TranslationService $translationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[\Override]
    public function getGlobals(): array
    {
        return [
            'enabled_locales' => $this->languageService->getEnabledCodes(),
        ];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_enabled_locales', $this->languageService->getEnabledCodes(...)),
            new TwigFunction('get_all_languages', $this->languageService->getAllLanguages(...)),
            new TwigFunction('current_locale', $this->getCurrentLocale(...)),
            new TwigFunction('get_alternative_languages', $this->getAlternativeLanguageCodes(...)),
            new TwigFunction('get_language_codes', $this->translationService->getLanguageCodes(...)),
        ];
    }

    public function getCurrentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? throw new \RuntimeException('Could not get current locale');
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
