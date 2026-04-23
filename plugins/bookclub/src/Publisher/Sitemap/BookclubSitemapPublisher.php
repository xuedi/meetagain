<?php declare(strict_types=1);

namespace Plugin\Bookclub\Publisher\Sitemap;

use App\Publisher\Sitemap\SitemapPublisherInterface;
use App\Publisher\Sitemap\SitemapUrl;
use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use DateTimeImmutable;
use Override;
use Plugin\Bookclub\Service\BookService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the public bookclub index plus a detail entry per approved book.
 *
 * BookService::getApprovedList already applies the multisite group filter, so
 * whitelabel tenants only see books their group can access.
 */
final readonly class BookclubSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
        private BookService $bookService,
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
        if (!in_array('bookclub', $this->pluginService->getActiveList(), true)) {
            return [];
        }

        $locales = $this->languageService->getFilteredEnabledCodes();

        return [
            ...$this->collectIndex($locales),
            ...$this->collectBooks($locales),
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
                'app_plugin_bookclub',
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

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectBooks(array $locales): array
    {
        $books = $this->bookService->getApprovedList();
        if ($books === []) {
            return [];
        }

        $urls = [];

        foreach ($books as $book) {
            $id = $book->getId();
            if ($id === null) {
                continue;
            }

            $createdAt = $book->getCreatedAt();
            $lastmod = $createdAt !== null
                ? new DateTimeImmutable($createdAt->format('Y-m-d'))
                : null;

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_plugin_bookclub_book_show',
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
                );
            }
        }

        return $urls;
    }
}
