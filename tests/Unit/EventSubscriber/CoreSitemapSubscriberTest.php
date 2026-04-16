<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\CoreSitemapSubscriber;
use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CoreSitemapSubscriberTest extends TestCase
{
    private Stub&EventRepository $eventRepo;
    private Stub&CmsRepository $cmsRepo;
    private Stub&LanguageService $languageService;
    private Stub&UrlGeneratorInterface $urlGenerator;
    private Stub&CmsFilterService $cmsFilterService;
    private CoreSitemapSubscriber $subject;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createStub(EventRepository::class);
        $this->cmsRepo = $this->createStub(CmsRepository::class);
        $this->languageService = $this->createStub(LanguageService::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $this->cmsFilterService = $this->createStub(CmsFilterService::class);

        $this->subject = new CoreSitemapSubscriber(
            $this->eventRepo,
            $this->cmsRepo,
            $this->languageService,
            $this->urlGenerator,
            $this->cmsFilterService,
        );
    }

    public function testPopulateStaticUsesLocaleCodeDirectlyAsHreflang(): void
    {
        // Arrange — enabled codes include zh; sitemap should emit hreflang="zh" not hreflang="cn"
        $this->languageService->method('getFilteredEnabledCodes')->willReturn(['en', 'zh']);

        $this->urlGenerator->method('generate')->willReturnCallback(
            static fn(string $route, array $params): string => sprintf('http://example.com/%s/', $params['_locale']),
        );

        /** @var list<Url> $capturedUrls */
        $capturedUrls = [];
        $urlContainer = new class ($capturedUrls) implements UrlContainerInterface {
            /** @param list<Url> $capturedUrls */
            public function __construct(private array &$capturedUrls) {}

            public function addUrl(Url $url, string $section): void
            {
                $this->capturedUrls[] = $url;
            }
        };

        $event = new SitemapPopulateEvent($urlContainer, $this->urlGenerator, 'static');

        // Act
        $this->subject->populate($event);

        // Assert — each URL entry must contain hreflang="zh" (not "cn") for the Chinese locale
        static::assertNotEmpty($capturedUrls, 'Expected at least one URL in the static sitemap section');
        foreach ($capturedUrls as $url) {
            $xml = $url->toXml();
            static::assertStringNotContainsString('hreflang="cn"', $xml, 'Locale "cn" must not appear in hreflang attributes after rename');
            static::assertStringContainsString('hreflang="zh"', $xml, 'Locale "zh" must appear in hreflang attributes');
        }
    }

    public function testGetSubscribedEventsListensToPrestaSitemapEvent(): void
    {
        $events = CoreSitemapSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SitemapPopulateEvent::class, $events);
    }
}
