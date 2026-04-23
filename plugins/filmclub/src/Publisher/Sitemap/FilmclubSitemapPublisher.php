<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the public film list to the sitemap.
 *
 * Filmclub does not currently expose a public per-film detail route -
 * `app_filmclub_film_new` is auth-gated and vote routes require ROLE_USER.
 */
final readonly class FilmclubSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
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
            );
        }

        return $urls;
    }
}
