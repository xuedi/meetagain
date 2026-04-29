<?php declare(strict_types=1);

namespace App\Publisher\Sitemap;

use App\Filter\Cms\CmsFilterService;
use App\Filter\Member\MemberFilterService;
use App\Filter\Sitemap\SitemapEventLocaleFilterInterface;
use App\Filter\Sitemap\SitemapEventVisibilityService;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Core sitemap URLs: public static routes, published CMS pages, published events.
 * Event URLs may be suppressed by registered SitemapEventVisibilityFilterInterface implementations.
 */
final readonly class CoreSitemapPublisher implements SitemapPublisherInterface
{
    private const int MEMBER_PAGE_SIZE = 24;
    private const int MEMBER_PAGE_CAP = 50;

    public function __construct(
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private UserRepository $userRepository,
        private LanguageService $languageService,
        private UrlGeneratorInterface $urlGenerator,
        private CmsFilterService $cmsFilterService,
        private MemberFilterService $memberFilterService,
        private SitemapEventVisibilityService $eventVisibilityService,
        #[AutowireIterator(SitemapEventLocaleFilterInterface::class)]
        private iterable $eventLocaleFilters = [],
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
            ...$this->collectMemberPages($locales),
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
            // Content entry points
            ['route' => 'app_default', 'params' => [], 'priority' => 1.0, 'changefreq' => 'daily'],
            ['route' => 'app_event', 'params' => [], 'priority' => 0.9, 'changefreq' => 'daily'],
            ['route' => 'app_event_featured', 'params' => [], 'priority' => 0.7, 'changefreq' => 'weekly'],
            // Static utility / docs
            ['route' => 'app_contact', 'params' => [], 'priority' => 0.5, 'changefreq' => 'yearly'],
            ['route' => 'app_api', 'params' => [], 'priority' => 0.6, 'changefreq' => 'weekly'],
            ['route' => 'app_cookie', 'params' => [], 'priority' => 0.3, 'changefreq' => 'yearly'],
            // Auth entry points - low priority so they don't steal crawl budget from content
            ['route' => 'app_login', 'params' => [], 'priority' => 0.3, 'changefreq' => 'yearly'],
            ['route' => 'app_register', 'params' => [], 'priority' => 0.3, 'changefreq' => 'yearly'],
            ['route' => 'app_reset', 'params' => [], 'priority' => 0.3, 'changefreq' => 'yearly'],
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
                    changefreq: $routeConfig['changefreq'],
                    priority: $routeConfig['priority'],
                    alternates: $localeUrls,
                );
            }
        }

        return $urls;
    }

    /**
     * One entry per page of the public member directory, applying the same
     * MemberFilterService restrictions an anonymous visitor would see.
     *
     * @param array<string> $locales
     * @return array<SitemapUrl>
     */
    private function collectMemberPages(array $locales): array
    {
        $filterResult = $this->memberFilterService->getUserIdFilter();
        $restrictToUserIds = $filterResult->getUserIds();

        $total = $this->userRepository->getNumberOfActivePublicMembers($restrictToUserIds);
        if ($total <= 0) {
            return [];
        }

        $pageCount = (int) min(self::MEMBER_PAGE_CAP, ceil($total / self::MEMBER_PAGE_SIZE));

        $urls = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $localeUrls = [];
            foreach ($locales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_member',
                    ['_locale' => $locale, 'page' => $page],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($locales as $locale) {
                $urls[] = new SitemapUrl(
                    loc: $localeUrls[$locale],
                    changefreq: 'weekly',
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
        $events = $this->eventRepository->findForSitemap();
        if ($events === []) {
            return [];
        }

        $eventIds = array_filter(array_map(static fn($e) => $e->getId(), $events));
        $allowedLocalesByEventId = $this->resolveAllowedLocalesByEventId(array_values($eventIds));

        $urls = [];

        foreach ($events as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }

            $lastmod = new DateTimeImmutable($event->getStart()->format('Y-m-d'));
            $eventLocales = $allowedLocalesByEventId[$id] ?? $locales;

            $localeUrls = [];
            foreach ($eventLocales as $locale) {
                $localeUrls[$locale] = $this->urlGenerator->generate(
                    'app_event_details',
                    ['_locale' => $locale, 'id' => $id],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }

            foreach ($eventLocales as $locale) {
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

    /**
     * @param int[] $eventIds
     * @return array<int, string[]> eventId => allowed locales (only for restricted events)
     */
    private function resolveAllowedLocalesByEventId(array $eventIds): array
    {
        $result = [];
        foreach ($this->eventLocaleFilters as $filter) {
            $filterResult = $filter->getAllowedLocalesByEventId($eventIds);
            if ($filterResult === null) {
                continue;
            }
            foreach ($filterResult as $eventId => $locales) {
                $result[$eventId] = isset($result[$eventId])
                    ? array_values(array_intersect($result[$eventId], $locales))
                    : $locales;
            }
        }

        return $result;
    }
}
