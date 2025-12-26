<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\LanguageService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class LanguageExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly LanguageService $languageService,
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
        ];
    }
}
