<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\Sitemap;

use App\Entity\Cms;
use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Enum\EventCanonicalRootType;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterService;
use App\Filter\Member\MemberFilterResult;
use App\Filter\Member\MemberFilterService;
use App\Filter\Sitemap\SitemapEventLocaleFilterInterface;
use App\Filter\Sitemap\SitemapEventVisibilityService;
use App\Publisher\Sitemap\CoreSitemapPublisher;
use App\Repository\CmsRepository;
use App\Repository\EventCanonicalRootRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Config\LanguageService;
use App\Service\Seo\EventCanonicalResolver;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Unit\Stubs\EventSeriesStub;
use Tests\Unit\Stubs\EventStub;

class CoreSitemapPublisherTest extends TestCase
{
    private const int STATIC_ROUTE_COUNT = 8;

    public function testEmitsStaticRoutesWithLocaleAlternates(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en', 'de'], cmsPages: [], events: [], cmsFilter: CmsFilterResult::noFilter(), shouldEmitEvents: true);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(self::STATIC_ROUTE_COUNT * 2, $urls);
        foreach ($urls as $url) {
            self::assertArrayHasKey('en', $url->alternates);
            self::assertArrayHasKey('de', $url->alternates);
        }
    }

    public function testEmitsAllExpectedStaticRoutes(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en'], cmsPages: [], events: [], cmsFilter: CmsFilterResult::noFilter(), shouldEmitEvents: true);

        // Act
        $urls = $publisher->getSitemapUrls();
        $locs = array_map(static fn($u) => $u->loc, $urls);

        // Assert
        $expected = [
            'app_default',
            'app_event',
            'app_event_featured',
            'app_contact',
            'app_cookie',
            'app_login',
            'app_register',
            'app_reset',
        ];
        foreach ($expected as $route) {
            self::assertNotEmpty(array_filter($locs, static fn($loc) => str_contains($loc, $route)), "expected route {$route} in sitemap");
        }
    }

    public function testAuthRoutesUseLowPriority(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en'], cmsPages: [], events: [], cmsFilter: CmsFilterResult::noFilter(), shouldEmitEvents: true);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        foreach ($urls as $url) {
            foreach (['app_login', 'app_register', 'app_reset', 'app_cookie'] as $route) {
                if (!str_contains($url->loc, $route)) {
                    continue;
                }

                self::assertSame(0.3, $url->priority, "{$route} should have priority 0.3");
            }
        }
    }

    public function testEmitsMemberPagesBasedOnMemberCount(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [],
            events: [],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            memberCount: 60,
        );

        // Act
        $urls = $publisher->getSitemapUrls();
        $memberUrls = array_filter($urls, static fn($u) => str_contains($u->loc, 'app_member'));

        // Assert
        self::assertCount(3, $memberUrls);
    }

    public function testEmitsNoMemberPagesWhenZeroMembers(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [],
            events: [],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            memberCount: 0,
        );

        // Act
        $urls = $publisher->getSitemapUrls();
        $memberUrls = array_filter($urls, static fn($u) => str_contains($u->loc, 'app_member'));

        // Assert
        self::assertEmpty($memberUrls);
    }

    public function testCapsMemberPaginationAtFifty(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [],
            events: [],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            memberCount: 100_000,
        );

        // Act
        $urls = $publisher->getSitemapUrls();
        $memberUrls = array_filter($urls, static fn($u) => str_contains($u->loc, 'app_member'));

        // Assert
        self::assertCount(50, $memberUrls);
    }

    public function testRespectsCmsFilterService(): void
    {
        // Arrange
        $page1 = $this->makeCmsPage(1, 'allowed');
        $page2 = $this->makeCmsPage(2, 'blocked');

        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [$page1, $page2],
            events: [],
            cmsFilter: new CmsFilterResult([1], true),
            shouldEmitEvents: true,
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        $locs = array_map(static fn($u) => $u->loc, $urls);
        $matching = array_filter($locs, static fn($loc) => str_contains($loc, 'allowed'));
        $blocked = array_filter($locs, static fn($loc) => str_contains($loc, 'blocked'));
        self::assertNotEmpty($matching);
        self::assertEmpty($blocked);
    }

    public function testSuppressesEventsWhenVisibilityFilterDenies(): void
    {
        // Arrange
        $event = $this->makeEvent(42, new DateTime('2026-05-01'));

        $publisher = $this->makePublisher(locales: ['en'], cmsPages: [], events: [$event], cmsFilter: CmsFilterResult::noFilter(), shouldEmitEvents: false);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        $locs = array_map(static fn($u) => $u->loc, $urls);
        foreach ($locs as $loc) {
            self::assertStringNotContainsString('/event/42', $loc);
        }
    }

    public function testEmitsEventsWithStartDateAsLastmod(): void
    {
        // Arrange
        $event = $this->makeEvent(42, new DateTime('2026-05-01'));

        $publisher = $this->makePublisher(locales: ['en'], cmsPages: [], events: [$event], cmsFilter: CmsFilterResult::noFilter(), shouldEmitEvents: true);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        $eventUrls = array_filter($urls, static fn($u) => str_contains($u->loc, '/event/42'));
        self::assertNotEmpty($eventUrls);
        foreach ($eventUrls as $u) {
            self::assertSame('2026-05-01', $u->lastmod?->format('Y-m-d'));
            self::assertSame(0.6, $u->priority);
        }
    }

    public function testOmitsEventsTheDetailPageWouldReject(): void
    {
        // Arrange
        $reachable = $this->makeEvent(42, new DateTime('2026-05-01'));
        $unreachable = $this->makeEvent(109, new DateTime('2026-05-02'));

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$reachable, $unreachable],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            accessibleEventIds: [42],
        );

        // Act
        $locs = array_map(static fn($u) => $u->loc, $publisher->getSitemapUrls());

        // Assert
        self::assertNotEmpty(array_filter($locs, static fn($loc) => str_contains($loc, '/event/42')));
        self::assertEmpty(array_filter($locs, static fn($loc) => str_contains($loc, '/event/109')));
    }

    public function testOmitsEventsFromAlternatesWhenNoneAreAccessible(): void
    {
        // Arrange
        $event = $this->makeEvent(109, new DateTime('2026-05-01'));

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$event],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            accessibleEventIds: [],
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        foreach ($urls as $url) {
            self::assertStringNotContainsString('/event/', $url->loc);
            foreach ($url->alternates as $href) {
                self::assertStringNotContainsString('/event/', $href);
            }
        }
    }

    public function testEmitsEveryMarkedRootOfASeries(): void
    {
        // Arrange
        $series = new EventSeriesStub();
        $series->setId(7);
        $first = $this->makeSeriesMember(1, new DateTime('2026-05-01'), $series);
        $follower = $this->makeSeriesMember(2, new DateTime('2026-05-08'), $series);
        $branched = $this->makeSeriesMember(3, new DateTime('2026-05-15'), $series);
        $afterBranch = $this->makeSeriesMember(4, new DateTime('2026-05-22'), $series);

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$first, $follower, $branched, $afterBranch],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            seriesMembers: [$first, $follower, $branched, $afterBranch],
            markers: [$this->makeMarker($branched, 'en', EventCanonicalRootType::Root), $this->makeMarker($branched, 'de', EventCanonicalRootType::Root)],
        );

        // Act
        $locs = array_values(array_map(
            static fn($u) => $u->loc,
            array_filter($publisher->getSitemapUrls(), static fn($u) => str_contains($u->loc, '/event/')),
        ));
        sort($locs);

        // Assert
        self::assertSame([
            'https://example.com/de/event/1',
            'https://example.com/de/event/3',
            'https://example.com/en/event/1',
            'https://example.com/en/event/3',
        ], $locs);
    }

    public function testEmitsMarkedRootsUnderAGroupLocaleToo(): void
    {
        // Arrange
        $series = new EventSeriesStub();
        $series->setId(7);
        $first = $this->makeSeriesMember(1, new DateTime('2026-05-01'), $series);
        $follower = $this->makeSeriesMember(2, new DateTime('2026-05-08'), $series);
        $branched = $this->makeSeriesMember(3, new DateTime('2026-05-15'), $series);

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$first, $follower, $branched],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            seriesMembers: [$first, $follower, $branched],
            allowedLocalesByEventId: [1 => ['de', 'es'], 2 => ['de', 'es'], 3 => ['de', 'es']],
            markers: [$this->makeMarker($branched, 'de', EventCanonicalRootType::Root)],
        );

        // Act
        $locs = array_values(array_map(
            static fn($u) => $u->loc,
            array_filter($publisher->getSitemapUrls(), static fn($u) => str_contains($u->loc, '/event/')),
        ));
        sort($locs);

        // Assert
        self::assertSame([
            'https://example.com/de/event/1',
            'https://example.com/de/event/3',
        ], $locs);
    }

    public function testDropsLocalesTheSiteDoesNotServe(): void
    {
        // Arrange
        $event = $this->makeEvent(42, new DateTime('2026-05-01'));

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$event],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            allowedLocalesByEventId: [42 => ['de', 'es']],
        );

        // Act
        $urls = array_values(array_filter($publisher->getSitemapUrls(), static fn($u) => str_contains($u->loc, '/event/')));

        // Assert
        self::assertCount(1, $urls);
        self::assertSame('https://example.com/de/event/42', $urls[0]->loc);
        self::assertSame(['de' => 'https://example.com/de/event/42'], $urls[0]->alternates);
    }

    public function testSeriesFollowersStayOmittedUnderAnUnservedLocale(): void
    {
        // Arrange
        $series = new EventSeriesStub();
        $series->setId(7);
        $root = $this->makeSeriesMember(1, new DateTime('2026-05-01'), $series);
        $follower = $this->makeSeriesMember(2, new DateTime('2026-05-08'), $series);

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$root, $follower],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            seriesMembers: [$root, $follower],
            allowedLocalesByEventId: [1 => ['de', 'es'], 2 => ['de', 'es']],
        );

        // Act
        $locs = array_map(
            static fn($u) => $u->loc,
            array_filter($publisher->getSitemapUrls(), static fn($u) => str_contains($u->loc, '/event/')),
        );

        // Assert
        self::assertSame(['https://example.com/de/event/1'], array_values($locs));
    }

    public function testSeriesFollowersAreOmittedAndAlternatesPointAtTheRoot(): void
    {
        // Arrange
        $series = new EventSeriesStub();
        $series->setId(7);
        $root = $this->makeSeriesMember(1, new DateTime('2026-05-01'), $series);
        $follower = $this->makeSeriesMember(2, new DateTime('2026-05-08'), $series);

        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [$root, $follower],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
            seriesMembers: [$root, $follower],
        );

        // Act
        $urls = array_values(array_filter($publisher->getSitemapUrls(), static fn($u) => str_contains($u->loc, '/event/')));

        // Assert
        self::assertCount(2, $urls);
        foreach ($urls as $url) {
            self::assertStringContainsString('/event/1', $url->loc);
            self::assertSame([
                'en' => 'https://example.com/en/event/1',
                'de' => 'https://example.com/de/event/1',
            ], $url->alternates);
        }
    }

    /**
     * @param array<string> $locales
     * @param array<Cms> $cmsPages
     * @param array<Event> $events
     * @param array<Event> $seriesMembers
     * @param int[]|null $accessibleEventIds null = every event stays accessible
     * @param array<int, string[]>|null $allowedLocalesByEventId null = no locale filter registered
     * @param array<EventCanonicalRoot> $markers
     */
    private function makePublisher(
        array $locales,
        array $cmsPages,
        array $events,
        CmsFilterResult $cmsFilter,
        bool $shouldEmitEvents,
        int $memberCount = 0,
        array $seriesMembers = [],
        ?array $accessibleEventIds = null,
        ?array $allowedLocalesByEventId = null,
        array $markers = [],
    ): CoreSitemapPublisher {
        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findForSitemap')->willReturn($events);
        $eventRepo->method('findSeriesMembers')->willReturn($seriesMembers);

        $cmsRepo = $this->createStub(CmsRepository::class);
        $cmsRepo->method('findPublished')->willReturn($cmsPages);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('getNumberOfActivePublicMembers')->willReturn($memberCount);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $params = []) {
                $locale = $params['_locale'] ?? 'en';
                $slug = $params['page'] ?? null;
                $id = $params['id'] ?? null;

                return match ($route) {
                    'app_member' => "https://example.com/{$locale}/{$route}/{$slug}",
                    'app_catch_all' => "https://example.com/{$locale}/{$slug}",
                    'app_event_details' => "https://example.com/{$locale}/event/{$id}",
                    default => "https://example.com/{$locale}/{$route}",
                };
            });

        $cmsFilterService = $this->createStub(CmsFilterService::class);
        $cmsFilterService->method('getCmsIdFilter')->willReturn($cmsFilter);

        $memberFilterService = $this->createStub(MemberFilterService::class);
        $memberFilterService->method('getUserIdFilter')->willReturn(MemberFilterResult::noFilter());

        $eventFilterService = $this->createStub(EventFilterService::class);
        $eventFilterService
            ->method('getAccessibleEventIds')
            ->willReturnCallback(
                static fn(array $ids) => $accessibleEventIds === null ? $ids : array_values(array_intersect($ids, $accessibleEventIds)),
            );

        $visibility = $this->createStub(SitemapEventVisibilityService::class);
        $visibility->method('shouldEmitEvents')->willReturn($shouldEmitEvents);

        $markerRepo = $this->createStub(EventCanonicalRootRepository::class);
        $markerRepo->method('findBySeriesIds')->willReturn($markers);
        $markerRepo->method('findBySeries')->willReturn($markers);

        $localeFilters = [];
        if ($allowedLocalesByEventId !== null) {
            $localeFilter = $this->createStub(SitemapEventLocaleFilterInterface::class);
            $localeFilter->method('getAllowedLocalesByEventId')->willReturn($allowedLocalesByEventId);
            $localeFilters[] = $localeFilter;
        }

        return new CoreSitemapPublisher(
            eventRepository: $eventRepo,
            cmsRepository: $cmsRepo,
            userRepository: $userRepo,
            languageService: $language,
            urlGenerator: $urlGenerator,
            cmsFilterService: $cmsFilterService,
            memberFilterService: $memberFilterService,
            eventFilterService: $eventFilterService,
            eventVisibilityService: $visibility,
            canonicalResolver: new EventCanonicalResolver($eventRepo, $markerRepo),
            eventLocaleFilters: $localeFilters,
        );
    }

    private function makeCmsPage(int $id, string $slug): Cms
    {
        $reflection = new \ReflectionClass(Cms::class);
        $page = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('id')->setValue($page, $id);
        $reflection->getProperty('slug')->setValue($page, $slug);
        $reflection->getProperty('createdAt')->setValue($page, new DateTimeImmutable('2026-04-01'));

        return $page;
    }

    private function makeMarker(Event $event, string $locale, EventCanonicalRootType $type): EventCanonicalRoot
    {
        $marker = new EventCanonicalRoot();
        $marker->setEvent($event);
        $marker->setLocale($locale);
        $marker->setType($type);

        return $marker;
    }

    private function makeSeriesMember(int $id, DateTimeInterface $start, EventSeries $series): Event
    {
        $event = new EventStub();
        $event->setId($id);
        $event->setStart($start);
        $event->setSeries($series);

        foreach (['en', 'de'] as $locale) {
            $translation = new EventTranslation();
            $translation->setLanguage($locale);
            $translation->setTitle('Weekly meetup');
            $translation->setDescription('Same content on every occurrence.');
            $event->addTranslation($translation);
        }

        return $event;
    }

    private function makeEvent(int $id, \DateTimeInterface $start): Event
    {
        $reflection = new \ReflectionClass(Event::class);
        $event = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('id')->setValue($event, $id);
        $reflection->getProperty('start')->setValue($event, $start);

        return $event;
    }
}
