<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LanguageExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_hreflang_code', static fn(string $code): string => $code),
            new TwigFunction('get_enabled_locales', [LanguageRuntime::class, 'getEnabledLocales']),
            new TwigFunction('current_locale', [LanguageRuntime::class, 'getCurrentLocale']),
            new TwigFunction('get_alternative_languages', [LanguageRuntime::class, 'getLanguageSwitcherOptions']),
            new TwigFunction('get_hreflang_languages', [LanguageRuntime::class, 'getHreflangLanguageCodes']),
            new TwigFunction('get_admin_language_codes', [LanguageRuntime::class, 'getAdminLanguageCodes']),
            new TwigFunction('route_exists', [LanguageRuntime::class, 'routeExists']),
            new TwigFunction('get_canonical_url', [LanguageRuntime::class, 'getCanonicalUrl']),
            new TwigFunction('get_site_name', [LanguageRuntime::class, 'getSiteName']),
            new TwigFunction('get_meta_description', [LanguageRuntime::class, 'getMetaDescription']),
            new TwigFunction('get_organization_schema', [LanguageRuntime::class, 'getOrganizationSchema'], [
                'is_safe' => ['html'],
            ]),
        ];
    }
}
