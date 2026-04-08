<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
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
        #[AutowireIterator(MetaDescriptionProviderInterface::class)]
        private readonly iterable $metaDescriptionProviders = [],
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
            new TwigFunction('get_hreflang_code', $this->languageService->toHreflangCode(...)),
            new TwigFunction('get_enabled_locales', $this->languageService->getFilteredEnabledCodes(...)),
            new TwigFunction('get_all_languages', $this->languageService->getAllLanguages(...)),
            new TwigFunction('current_locale', $this->getCurrentLocale(...)),
            new TwigFunction('get_alternative_languages', $this->getAlternativeLanguageCodes(...)),
            new TwigFunction('get_language_codes', $this->languageService->getFilteredEnabledCodes(...)),
            new TwigFunction('get_admin_language_codes', $this->languageService->getAdminFilteredEnabledCodes(...)),
            new TwigFunction('route_exists', $this->routeExists(...)),
            new TwigFunction('get_canonical_url', $this->getCanonicalUrl(...)),
            new TwigFunction('get_meta_description', $this->getMetaDescription(...)),
        ];
    }

    public function getCurrentLocale(): string
    {
        return (
            $this->requestStack->getCurrentRequest()?->getLocale() ?? throw new RuntimeException(
                'Could not get current locale',
            )
        );
    }

    public function getAlternativeLanguageCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $currentUri = $request->getRequestUri();
            $currentLocale = $request->getLocale();
            if (!str_starts_with($currentUri, '/_profiler')) {
                $altLangList = $this->languageService->getAltLangList($currentLocale, $currentUri);
                $host = rtrim($this->configService->getHost(), '/');

                return array_map(fn(string $path) => $host . $path, $altLangList);
            }
        }

        return [];
    }

    public function getMetaDescription(string $context = 'default'): string
    {
        // 1. Plugin-provided value (e.g. per-group override from multisite)
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
        $path = $request instanceof Request ? $request->getRequestUri() : '/';

        return rtrim($this->configService->getHost(), '/') . $path;
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
