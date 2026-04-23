<?php declare(strict_types=1);

namespace Plugin\Glossary\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the public glossary index to the sitemap.
 *
 * Detail/edit/delete routes are auth-gated (ROLE_USER / ROLE_ORGANIZER) so only
 * the index page belongs in the sitemap.
 */
final readonly class GlossarySitemapPublisher implements SitemapPublisherInterface
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
        if (!in_array('glossary', $this->pluginService->getActiveList(), true)) {
            return [];
        }

        $locales = $this->languageService->getFilteredEnabledCodes();

        $localeUrls = [];
        foreach ($locales as $locale) {
            $localeUrls[$locale] = $this->urlGenerator->generate(
                'app_plugin_glossary',
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
