<?php declare(strict_types=1);

namespace App\Twig;

use App\Filter\Language\AlternateLinkFilterInterface;
use App\Publisher\AlternateLinks\AlternateLinkProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class LanguageRuntime implements RuntimeExtensionInterface
{
    /**
     * @param iterable<AlternateLinkFilterInterface>   $alternateLinkFilters
     * @param iterable<AlternateLinkProviderInterface> $alternateLinkProviders
     */
    public function __construct(
        private LanguageService $languageService,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private ConfigService $configService,
        #[AutowireIterator(AlternateLinkFilterInterface::class)]
        private iterable $alternateLinkFilters = [],
        #[AutowireIterator(AlternateLinkProviderInterface::class)]
        private iterable $alternateLinkProviders = [],
    ) {}

    /** @return list<string> */
    public function getEnabledLocales(): array
    {
        return $this->languageService->getFilteredEnabledCodes();
    }

    /** @return list<string> */
    public function getAdminLanguageCodes(): array
    {
        return $this->languageService->getAdminFilteredEnabledCodes();
    }

    public function getCurrentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? throw new RuntimeException('Could not get current locale');
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

        // The language switcher lists the other languages; an hreflang cluster must also name the
        // page itself, otherwise search engines discard the whole cluster.
        $altLangList = $this->languageService->getAltLangList($request->getLocale(), $currentUri);
        $altLangList[$request->getLocale()] = $this->languageService->replaceUriLanguageCode($currentUri, $request->getLocale());

        $host = rtrim($this->configService->getHost(), '/');
        $localeUrls = array_map(static fn(string $path) => $host . $path, $altLangList);

        foreach ($this->alternateLinkProviders as $provider) {
            $provided = $provider->getAlternateLinks($localeUrls, $request);
            if ($provided !== null) {
                $localeUrls = $provided;
                break;
            }
        }

        return $this->applyAlternateLinkFilters($localeUrls, $request);
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
