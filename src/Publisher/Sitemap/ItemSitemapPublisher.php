<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use App\Item\ListProviderInterface;
use App\Item\ListRegistry;
use App\Item\Tag\FacetService;
use App\Service\Config\LanguageService;
use App\Service\Seo\UrlOwnerService;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ItemSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
        private ListRegistry $listRegistry,
        private FacetService $facetService,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        private UrlOwnerService $urlOwnerService,
    ) {}

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return array<SitemapUrl>
     */
    #[Override]
    public function getSitemapUrls(): array
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        if ($locales === []) {
            return [];
        }

        $urls = [];
        foreach ($this->listRegistry->activeProviders() as $itemType => $provider) {
            $urls = [
                ...$urls,
                ...$this->collectListPage($itemType, $provider, $locales),
                ...$this->collectDetailPages($itemType, $provider, $locales),
            ];
        }

        return $urls;
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectListPage(string $itemType, ListProviderInterface $provider, array $locales): array
    {
        $route = $provider->getListRoute();
        $localeUrls = $this->generatePerLocale($route, [], $locales);
        if (!$this->isServedHere($route, [], $localeUrls)) {
            return [];
        }

        $urls = [];
        foreach ($locales as $locale) {
            $urls[] = new SitemapUrl(
                loc: $localeUrls[$locale],
                changefreq: 'weekly',
                priority: 0.7,
                alternates: $localeUrls,
                section: 'items',
                locale: $locale,
                meta: ['item_type' => $itemType, 'route' => $route],
            );
        }

        return $urls;
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectDetailPages(string $itemType, ListProviderInterface $provider, array $locales): array
    {
        $route = $provider->getDetailRoute();
        if ($route === null || !$provider->isDetailIndexable()) {
            return [];
        }

        $itemIds = $this->facetService->withoutFacets($provider->getItemIds(...));
        $lastmodByItemId = $this->facetService->withoutFacets(static fn(): array => $provider->getLastmodByItemId($itemIds));

        $urls = [];
        foreach ($itemIds as $itemId) {
            $localeUrls = $this->generatePerLocale($route, ['id' => $itemId], $locales);
            if (!$this->isServedHere($route, ['id' => $itemId], $localeUrls)) {
                continue;
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    lastmod: $lastmodByItemId[$itemId] ?? null,
                    changefreq: 'monthly',
                    priority: 0.5,
                    alternates: $localeUrls,
                    section: 'items',
                    locale: $locale,
                    meta: ['item_type' => $itemType, 'item_id' => $itemId],
                );
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $localeUrls
     */
    private function isServedHere(string $route, array $parameters, array $localeUrls): bool
    {
        $anyUrl = reset($localeUrls);

        return $anyUrl !== false && $this->urlOwnerService->ownsUrl($route, $parameters, $anyUrl);
    }

    /**
     * @param array<string, int|string> $params
     * @param array<string> $locales
     * @return array<string, string>
     */
    private function generatePerLocale(string $route, array $params, array $locales): array
    {
        $urls = [];
        foreach ($locales as $locale) {
            $urls[$locale] = $this->urlGenerator->generate(
                $route,
                ['_locale' => $locale, ...$params],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $urls;
    }
}
