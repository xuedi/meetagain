<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use Override;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\SelectionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the public film list and a detail entry per approved film to the sitemap.
 * Routes through FilmService::getApprovedList() so the filter chain restricts
 * the result set when a FilmGroupFilterInterface implementation narrows visibility.
 */
final readonly class FilmclubSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
        private FilmService $filmService,
        private SelectionService $selectionService,
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
        if (!in_array('filmclub', $this->pluginService->getActiveList(), true)) {
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
            $localeUrls[$locale] = $this->urlGenerator->generate(
                'app_filmclub_filmlist',
                ['_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        $urls = [];
        foreach ($locales as $locale) {
            $urls[] = new SitemapUrl(
                loc: $localeUrls[$locale],
                changefreq: 'weekly',
                priority: 0.7,
                alternates: $localeUrls,
                section: 'filmclub',
                locale: $locale,
                meta: ['route' => 'app_filmclub_filmlist'],
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
        $films = $this->filmService->getApprovedList();
        if ($films === []) {
            return [];
        }

        $urls = [];

        foreach ($films as $film) {
            $id = $film->getId();
            if ($id === null) {
                continue;
            }

            $selections = $this->selectionService->getSelectionsForFilm($film);
            $latestSelection = $selections[0] ?? null;

            $lastmod = $latestSelection?->getSelectedAt() ?? $film->getCreatedAt();

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_plugin_filmclub_film_show',
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
                    section: 'filmclub',
                    locale: $locale,
                    meta: ['film_id' => $id, 'title' => (string) $film->getTitle()],
                );
            }
        }

        return $urls;
    }
}
