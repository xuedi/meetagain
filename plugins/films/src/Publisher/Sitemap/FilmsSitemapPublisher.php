<?php declare(strict_types=1);

namespace Plugin\Films\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use Override;
use Plugin\Films\Service\FilmService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the public film list and a detail entry per film to the sitemap.
 * Routes through FilmService::getList() so the item filter chain restricts
 * the result set when a visibility filter narrows the allowed films.
 */
final readonly class FilmsSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
        private FilmService $filmService,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        private PluginService $pluginService,
    ) {}

    #[Override]
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @return array<SitemapUrl>
     */
    #[Override]
    public function getSitemapUrls(): array
    {
        if (!in_array('films', $this->pluginService->getActiveList(), true)) {
            return [];
        }

        $locales = $this->languageService->getFilteredEnabledCodes();

        return [
            ...$this->collectIndex($locales),
            ...$this->collectFilms($locales),
        ];
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectIndex(array $locales): array
    {
        $localeUrls = [];
        foreach ($locales as $locale) {
            $localeUrls[$locale] = $this->urlGenerator->generate('app_films_filmlist', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $urls = [];
        foreach ($locales as $locale) {
            $urls[] = new SitemapUrl(
                loc: $localeUrls[$locale],
                changefreq: 'weekly',
                priority: 0.7,
                alternates: $localeUrls,
                section: 'films',
                locale: $locale,
                meta: ['route' => 'app_films_filmlist'],
            );
        }

        return $urls;
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectFilms(array $locales): array
    {
        $films = $this->filmService->getList();
        if ($films === []) {
            return [];
        }

        $urls = [];

        foreach ($films as $film) {
            $id = $film->getId();
            if ($id === null) {
                continue;
            }

            $lastmod = $film->getCreatedAt();

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_plugin_films_film_show',
                    ['_locale' => $locale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    lastmod: $lastmod,
                    changefreq: 'monthly',
                    priority: 0.5,
                    alternates: $localeUrls,
                    section: 'films',
                    locale: $locale,
                    meta: ['film_id' => $id, 'title' => (string) $film->getTitle()],
                );
            }
        }

        return $urls;
    }
}
