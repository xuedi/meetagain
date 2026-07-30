<?php declare(strict_types=1);

namespace App\Service\Seo;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BreadcrumbBuilder
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
    ) {}

    /**
     * @return list<array{label: string, url?: string}>
     */
    public function build(string $sectionRoute, string $sectionLabelKey, string $currentLabel): array
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();

        return [
            ['label' => $this->translator->trans('chrome.menu_default'), 'url' => $this->absoluteUrl('app_default', $locale)],
            ['label' => $this->translator->trans($sectionLabelKey), 'url' => $this->absoluteUrl($sectionRoute, $locale)],
            ['label' => $currentLabel],
        ];
    }

    private function absoluteUrl(string $route, ?string $locale): string
    {
        return $this->urlGenerator->generate(
            $route,
            $locale !== null ? ['_locale' => $locale] : [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
