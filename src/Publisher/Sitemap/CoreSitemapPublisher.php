<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use App\Filter\Cms\CmsFilterService;
use App\Filter\Sitemap\SitemapEventVisibilityService;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Core sitemap URLs: public static routes, published CMS pages, published events.
 *
 * Events are suppressed when any `SitemapEventVisibilityFilterInterface` implementation
 * returns false (e.g. multisite plugin on whitelabel hosts, where events are
 * platform-canonical).
 */
final readonly class CoreSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        private CmsFilterService $cmsFilterService,
        private SitemapEventVisibilityService $eventVisibilityService,
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

        return [
            ...$this->collectStaticRoutes($locales),
            ...$this->collectCmsPages($locales),
            ...($this->eventVisibilityService->shouldEmitEvents() ? $this->collectEvents($locales) : []),
        ];
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectStaticRoutes(array $locales): array
    {
        $staticRoutes = [
            ['route' => 'app_default', 'params' => [], 'priority' => 1.0],
            ['route' => 'app_event', 'params' => [], 'priority' => 0.9],
            ['route' => 'app_member', 'params' => ['page' => 1], 'priority' => 0.7],
        ];

        $urls = [];

        foreach ($staticRoutes as $routeConfig) {
            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    $routeConfig['route'],
                    ['_locale' => $locale, ...$routeConfig['params']],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    priority: $routeConfig['priority'],
                    alternates: $localeUrls,
                );
            }
        }

        return $urls;
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectCmsPages(array $locales): array
    {
        $pages = $this->cmsRepository->findPublished();

        $filterResult = $this->cmsFilterService->getCmsIdFilter();
        if ($filterResult->hasActiveFilter()) {
            $allowedIdSet = array_flip($filterResult->getCmsIds() ?? []);
            $pages = array_filter($pages, static fn($p) => isset($allowedIdSet[$p->getId()]));
        }

        $urls = [];

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }

            $lastmod = new DateTimeImmutable($page->getCreatedAt()?->format('Y-m-d') ?? date('Y-m-d'));

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_catch_all',
                    ['_locale' => $locale, 'page' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    lastmod: $lastmod,
                    priority: 0.7,
                    alternates: $localeUrls,
                );
            }
        }

        return $urls;
    }

    /**
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectEvents(array $locales): array
    {
        $urls = [];

        foreach ($this->eventRepository->findForSitemap() as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }

            $lastmod = new DateTimeImmutable($event->getStart()->format('Y-m-d'));

            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_event_details',
                    ['_locale' => $locale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    lastmod: $lastmod,
                    priority: 0.6,
                    alternates: $localeUrls,
                );
            }
        }

        return $urls;
    }
}
