<?php

declare(strict_types=1);

namespace App\Twig;

use App\Filter\Language\AlternateLinkFilterInterface;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\CanonicalUrlService;
use Exception;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class LanguageExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly ConfigService $configService,
        private readonly CanonicalUrlService $canonicalUrlService,
        #[AutowireIterator(MetaDescriptionProviderInterface::class)]
        private readonly iterable $metaDescriptionProviders = [],
        #[AutowireIterator(OrganizationSchemaProviderInterface::class)]
        private readonly iterable $organizationProviders = [],
        #[AutowireIterator(AlternateLinkFilterInterface::class)]
        private readonly iterable $alternateLinkFilters = [],
    ) {}

    #[Override]
    public function getGlobals(): array
    {
        return [
            'enabled_locales' => $this->languageService->getFilteredEnabledCodes(),
        ];
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_hreflang_code', static fn(string $code): string => $code),
            new TwigFunction('get_enabled_locales', $this->languageService->getFilteredEnabledCodes(...)),
            new TwigFunction('get_all_languages', $this->languageService->getAllLanguages(...)),
            new TwigFunction('current_locale', $this->getCurrentLocale(...)),
            new TwigFunction('get_alternative_languages', $this->getLanguageSwitcherOptions(...)),
            new TwigFunction('get_hreflang_languages', $this->getHreflangLanguageCodes(...)),
            new TwigFunction('get_language_codes', $this->languageService->getFilteredEnabledCodes(...)),
            new TwigFunction('get_admin_language_codes', $this->languageService->getAdminFilteredEnabledCodes(...)),
            new TwigFunction('route_exists', $this->routeExists(...)),
            new TwigFunction('get_canonical_url', $this->getCanonicalUrl(...)),
            new TwigFunction('get_meta_description', $this->getMetaDescription(...)),
            new TwigFunction('get_organization_schema', $this->getOrganizationSchema(...), ['is_safe' => ['html']]),
            new TwigFunction('is_frontpage', $this->isFrontpage(...)),
        ];
    }

    public function isFrontpage(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        return $request->attributes->get('_route') === 'app_frontpage';
    }

    public function getCurrentLocale(): string
    {
        return (
            $this->requestStack->getCurrentRequest()?->getLocale() ?? throw new RuntimeException(
                'Could not get current locale',
            )
        );
    }

    public function getLanguageSwitcherOptions(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        $currentUri = $request->getRequestUri();
        if (str_starts_with($currentUri, '/_profiler')) {
            return [];
        }

        $allAltLangList = $this->languageService->getAltLangList($request->getLocale(), $currentUri);
        $supportedLocales = array_flip(array_keys($this->applyAlternateLinkFilters($allAltLangList, $request)));
        $host = $request->getSchemeAndHttpHost();

        $result = [];
        foreach ($allAltLangList as $locale => $path) {
            $result[$locale] = $host . (isset($supportedLocales[$locale]) ? $path : '/' . $locale . '/');
        }

        return $result;
    }

    public function getHreflangLanguageCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        $currentUri = $request->getRequestUri();
        if (str_starts_with($currentUri, '/_profiler')) {
            return [];
        }

        $altLangList = $this->languageService->getAltLangList($request->getLocale(), $currentUri);
        $altLangList = $this->applyAlternateLinkFilters($altLangList, $request);
        $host = rtrim($this->configService->getHost(), '/');

        return array_map(static fn(string $path) => $host . $path, $altLangList);
    }

    /**
     * @param array<string, string> $altLangList locale => path
     * @return array<string, string>
     */
    private function applyAlternateLinkFilters(array $altLangList, Request $request): array
    {
        foreach ($this->alternateLinkFilters as $filter) {
            $allowed = $filter->getAllowedAlternateLocaleCodes($request);
            if ($allowed !== null) {
                $altLangList = array_intersect_key($altLangList, array_flip($allowed));
            }
        }

        return $altLangList;
    }

    public function getMetaDescription(string $context = 'default'): string
    {
        // 1. Plugin-provided value
        foreach ($this->metaDescriptionProviders as $provider) {
            $value = $provider->getMetaDescription($context);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        // 2. System-wide admin config
        $systemValue = $this->configService->getSeoDescription($context);
        if ($systemValue !== '') {
            return $systemValue;
        }

        // 3. Hardcoded fallback
        return match ($context) {
            'events' => 'Browse upcoming events and meetups.',
            'members' => 'Meet the members of this community.',
            default => 'A community platform for local events and meetups.',
        };
    }

    public function getCanonicalUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return rtrim($this->configService->getHost(), '/') . '/';
        }

        return $this->canonicalUrlService->getCanonicalUrl($request);
    }

    public function getOrganizationSchema(): string
    {
        foreach ($this->organizationProviders as $provider) {
            $schema = $provider->getOrganizationSchema();
            if ($schema !== null) {
                return (
                    json_encode(array_merge(
                        ['@context' => 'https://schema.org'],
                        is_array($schema) ? $schema : [],
                    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
                );
            }
        }

        $host = rtrim($this->configService->getHost(), '/');

        return (
            json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $host . '/#organization',
                'name' => 'MeetAgain',
                'url' => $host,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );
    }

    public function routeExists(string $name): bool
    {
        try {
            $this->router->generate($name);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        } catch (Exception) {
            return true;
        }
    }
}
