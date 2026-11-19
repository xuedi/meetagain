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
        private UrlOwnerService $urlOwnerService,
    ) {}

    /**
     * @return list<array{label: string, url?: string}>
     */
    public function build(string $sectionRoute, string $sectionLabelKey, string $currentLabel): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getLocale();
        $host = $request !== null ? $this->urlOwnerService->getOwnerHostForRequest($request) : $this->urlOwnerService->getOwnerHost('app_default');

        return [
            ['label' => $this->translator->trans('chrome.menu_default'), 'url' => $host . $this->path('app_default', $locale)],
            ['label' => $this->translator->trans($sectionLabelKey), 'url' => $host . $this->path($sectionRoute, $locale)],
            ['label' => $currentLabel],
        ];
    }

    private function path(string $route, ?string $locale): string
    {
        return $this->urlGenerator->generate($route, $locale !== null ? ['_locale' => $locale] : [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
