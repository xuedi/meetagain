<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\Sitemap;

use App\Entity\Cms;
use App\Entity\Event;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use App\Filter\Sitemap\SitemapEventVisibilityService;
use App\Publisher\Sitemap\CoreSitemapPublisher;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CoreSitemapPublisherTest extends TestCase
{
    public function testEmitsStaticRoutesWithLocaleAlternates(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            cmsPages: [],
            events: [],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: 3 static routes x 2 locales = 6 URL entries; each has 2 alternates.
        self::assertCount(6, $urls);
        foreach ($urls as $url) {
            self::assertArrayHasKey('en', $url->alternates);
            self::assertArrayHasKey('de', $url->alternates);
        }
    }

    public function testRespectsCmsFilterService(): void
    {
        // Arrange: two published pages, but the filter only allows id=1.
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

        // Assert: only `allowed` appears (plus 3 static routes). 'blocked' does not.
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

        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [],
            events: [$event],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: false,
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: no event URLs in the output.
        $locs = array_map(static fn($u) => $u->loc, $urls);
        foreach ($locs as $loc) {
            self::assertStringNotContainsString('/event/42', $loc);
        }
    }

    public function testEmitsEventsWithStartDateAsLastmod(): void
    {
        // Arrange
        $event = $this->makeEvent(42, new DateTime('2026-05-01'));

        $publisher = $this->makePublisher(
            locales: ['en'],
            cmsPages: [],
            events: [$event],
            cmsFilter: CmsFilterResult::noFilter(),
            shouldEmitEvents: true,
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: at least one URL is an event URL with lastmod = event start.
        $eventUrls = array_filter($urls, static fn($u) => str_contains($u->loc, '/event/42'));
        self::assertNotEmpty($eventUrls);
        foreach ($eventUrls as $u) {
            self::assertSame('2026-05-01', $u->lastmod?->format('Y-m-d'));
            self::assertSame(0.6, $u->priority);
        }
    }

    /**
     * @param array<string> $locales
     * @param array<Cms> $cmsPages
     * @param array<Event> $events
     */
    private function makePublisher(
        array $locales,
        array $cmsPages,
        array $events,
        CmsFilterResult $cmsFilter,
        bool $shouldEmitEvents,
    ): CoreSitemapPublisher {
        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findForSitemap')->willReturn($events);

        $cmsRepo = $this->createStub(CmsRepository::class);
        $cmsRepo->method('findPublished')->willReturn($cmsPages);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []) {
                $locale = $params['_locale'] ?? 'en';
                $slug = $params['page'] ?? null;
                $id = $params['id'] ?? null;

                return match ($route) {
                    'app_default' => "https://example.com/{$locale}/",
                    'app_event' => "https://example.com/{$locale}/event",
                    'app_member' => "https://example.com/{$locale}/member/1",
                    'app_catch_all' => "https://example.com/{$locale}/{$slug}",
                    'app_event_details' => "https://example.com/{$locale}/event/{$id}",
                    default => "https://example.com/{$locale}/{$route}",
                };
            },
        );

        $cmsFilterService = $this->createStub(CmsFilterService::class);
        $cmsFilterService->method('getCmsIdFilter')->willReturn($cmsFilter);

        $visibility = $this->createStub(SitemapEventVisibilityService::class);
        $visibility->method('shouldEmitEvents')->willReturn($shouldEmitEvents);

        return new CoreSitemapPublisher(
            eventRepository: $eventRepo,
            cmsRepository: $cmsRepo,
            languageService: $language,
            urlGenerator: $urlGenerator,
            cmsFilterService: $cmsFilterService,
            eventVisibilityService: $visibility,
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

    private function makeEvent(int $id, \DateTimeInterface $start): Event
    {
        $reflection = new \ReflectionClass(Event::class);
        $event = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('id')->setValue($event, $id);
        $reflection->getProperty('start')->setValue($event, $start);

        return $event;
    }
}
