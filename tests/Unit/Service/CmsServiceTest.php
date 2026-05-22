<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterResult;
use App\Filter\Event\EventFilterService;
use App\Repository\CmsRepository;
use App\Service\Cms\CmsService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;

class CmsServiceTest extends TestCase
{
    public function testGetSitesReturnsAllCmsPages(): void
    {
        // Arrange: mock repository to return list of CMS pages
        $expectedSites = [
            $this->createStub(Cms::class),
            $this->createStub(Cms::class),
        ];

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock->expects($this->once())->method('findAll')->willReturn($expectedSites);

        $subject = new CmsService(
            twig: $this->createStub(Environment::class),
            repo: $cmsRepoMock,
            eventFilterService: $this->createStub(EventFilterService::class),
            cmsFilterService: $this->createStub(CmsFilterService::class),
            cache: $this->createStub(TagAwareCacheInterface::class),
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Act: get all sites
        $result = $subject->getSites();

        // Assert: returns array of CMS pages
        static::assertSame($expectedSites, $result);
    }

    public function testHandleThrowsNotFoundWhenPageNotFound(): void
    {
        // Arrange: repository returns null (slug miss)
        $locale = 'en';
        $slug = 'non-existent-page';

        $cmsFilterServiceMock = $this->createMock(CmsFilterService::class);
        $cmsFilterServiceMock->expects($this->once())->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock->expects($this->once())->method('findPublishedBySlug')->with($slug, null)->willReturn(null);

        $twigMock = $this->createMock(Environment::class);
        $twigMock->expects($this->never())->method('render');

        // Arrange: cache must NOT be touched on the 404 path
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->never())->method('get');

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventFilterService: $this->createStub(EventFilterService::class),
            cmsFilterService: $cmsFilterServiceMock,
            cache: $cacheMock,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Assert: handle() throws so the framework error pipeline can render the 404
        $this->expectException(NotFoundHttpException::class);

        // Act
        $subject->handle($locale, $slug, new Response());
    }

    public function testHandleReturns204WhenPageHasNoContentInRequestedLanguage(): void
    {
        // Arrange: mock CMS page with no content blocks for requested locale
        $locale = 'en';
        $slug = 'existing-page';
        $expectedContent = '204 page content';

        $cmsMock = $this->createMock(Cms::class);
        $cmsMock->expects($this->once())->method('getLanguageFilteredBlockJsonList')->with($locale)->willReturn(new ArrayCollection());
        $cmsMock->method('getId')->willReturn(11);

        $cmsFilterServiceMock = $this->createMock(CmsFilterService::class);
        $cmsFilterServiceMock->expects($this->once())->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock->expects($this->once())->method('findPublishedBySlug')->with($slug, null)->willReturn($cmsMock);

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/204.html.twig', ['message' => 'cms.error_204_default_message'])
            ->willReturn($expectedContent);

        // Arrange: cache miss; the 204 path must NOT write to the cache (single get(), no beta=INF store)
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback): string {
                return $callback(self::createCacheItem());
            });

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceMock,
            cache: $cacheMock,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Act: handle request for page without content in requested language
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 204 No Content response
        static::assertSame($expectedContent, $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testHandleReturns200WithContentWhenPageExists(): void
    {
        // Arrange: mock CMS page with content blocks; cache miss path renders both inner and outer templates
        $locale = 'en';
        $slug = 'existing-page';
        $pageTitle = 'Page Title';
        $expectedContent = 'rendered page content';
        $blocks = new ArrayCollection(['block1', 'block2']);

        $cmsMock = $this->createMock(Cms::class);
        $cmsMock->expects($this->once())->method('getLanguageFilteredBlockJsonList')->with($locale)->willReturn($blocks);
        $cmsMock->expects($this->once())->method('getPageTitle')->with($locale)->willReturn($pageTitle);
        $cmsMock->method('getId')->willReturn(123);

        $cmsFilterServiceMock = $this->createMock(CmsFilterService::class);
        $cmsFilterServiceMock->expects($this->once())->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceMock = $this->createMock(EventFilterService::class);
        $eventFilterServiceMock->expects($this->once())->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock->expects($this->once())->method('findPublishedBySlug')->with($slug, null)->willReturn($cmsMock);

        $renderedBody = '<inner-body/>';
        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $name, array $context) use ($blocks, $pageTitle, $renderedBody, $expectedContent): string {
                if ($name === 'cms/_blocks.html.twig') {
                    static::assertSame(['blocks' => $blocks], $context);
                    return $renderedBody;
                }
                static::assertSame('cms/index.html.twig', $name);
                static::assertSame(['title' => $pageTitle, 'body' => $renderedBody], $context);
                return $expectedContent;
            });

        // Arrange: cache miss + store; capture key shape and tags
        $capturedKey = null;
        $capturedTags = null;
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback, ?float $beta = null) use (&$capturedKey, &$capturedTags): string {
                $capturedKey = $key;
                $item = new class implements ItemInterface {
                    /** @var list<string> */
                    private array $tags = [];

                    public function getKey(): string
                    {
                        return '';
                    }

                    public function get(): mixed
                    {
                        return null;
                    }

                    public function isHit(): bool
                    {
                        return false;
                    }

                    public function set(mixed $value): static
                    {
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(int|\DateInterval|null $time): static
                    {
                        return $this;
                    }

                    public function tag(string|iterable $tags): static
                    {
                        $this->tags = is_string($tags) ? [$tags] : [...$tags];
                        return $this;
                    }

                    public function getMetadata(): array
                    {
                        return [];
                    }

                    /** @return list<string> */
                    public function getTags(): array
                    {
                        return $this->tags;
                    }
                };
                $result = $callback($item);
                if ($beta === \INF) {
                    $capturedTags = $item->getTags();
                }
                return $result;
            });

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventFilterService: $eventFilterServiceMock,
            cmsFilterService: $cmsFilterServiceMock,
            cache: $cacheMock,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Act: handle request for page with content
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 200 OK response with rendered content
        static::assertSame($expectedContent, $response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($capturedKey);
        static::assertStringStartsWith('cms_page.123.', $capturedKey);
        static::assertSame(['cms_page_123', 'cms_page_all'], $capturedTags);
    }

    public function testHandleUsesDefaultTitleWhenPageTitleIsNull(): void
    {
        // Arrange: mock CMS page with null title
        $locale = 'en';
        $slug = 'page-without-title';
        $expectedContent = 'rendered page content';
        $blocks = new ArrayCollection(['block1']);

        // Arrange: stub CMS entity to return blocks and null title
        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn($blocks);
        $cmsStub->method('getPageTitle')->willReturn(null);
        $cmsStub->method('getId')->willReturn(456);

        // Arrange: stub repository to return the CMS entity
        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findPublishedBySlug')->willReturn($cmsStub);

        // Arrange: stub filter services
        $cmsFilterServiceStub = $this->createStub(CmsFilterService::class);
        $cmsFilterServiceStub->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        // Arrange: mock Twig to verify default title is used
        $renderedBody = '<inner-body/>';
        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $name, array $context) use ($blocks, $renderedBody, $expectedContent): string {
                if ($name === 'cms/_blocks.html.twig') {
                    static::assertSame(['blocks' => $blocks], $context);
                    return $renderedBody;
                }
                static::assertSame('cms/index.html.twig', $name);
                static::assertSame(['title' => 'cms.page_no_title_fallback', 'body' => $renderedBody], $context);
                return $expectedContent;
            });

        $cacheStub = $this->createStub(TagAwareCacheInterface::class);
        $cacheStub
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback, ?float $beta = null): string {
                return $callback(self::createCacheItem());
            });

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoStub,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceStub,
            cache: $cacheStub,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Act: handle request for page without title
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: uses default title translation key 'cms.page_no_title_fallback'
        static::assertSame($expectedContent, $response->getContent());
    }

    public function testHandleReturnsCachedBodyWithoutFetchingBlocksOnCacheHit(): void
    {
        // Arrange: a cache hit should skip block fetch entirely and reuse the stored inner HTML
        $locale = 'en';
        $slug = 'privacy';
        $pageTitle = 'Privacy';
        $cachedBody = '<p>cached</p>';
        $outerHtml = '<html>cached page</html>';

        $cmsMock = $this->createMock(Cms::class);
        $cmsMock->expects($this->never())->method('getLanguageFilteredBlockJsonList');
        $cmsMock->method('getId')->willReturn(7);
        $cmsMock->expects($this->once())->method('getPageTitle')->with($locale)->willReturn($pageTitle);

        $cmsFilterServiceStub = $this->createStub(CmsFilterService::class);
        $cmsFilterServiceStub->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findPublishedBySlug')->willReturn($cmsMock);

        // Arrange: cache hit returns the cached body directly without invoking the miss callback
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static fn(string $key, callable $callback): string => $cachedBody);

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/index.html.twig', ['title' => $pageTitle, 'body' => $cachedBody])
            ->willReturn($outerHtml);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoStub,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceStub,
            cache: $cacheMock,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );

        // Act
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 200 with the rendered outer HTML; block fetch never happened
        static::assertSame($outerHtml, $response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleCacheKeyIncludesHostFromCurrentRequest(): void
    {
        // Arrange: the host segment of the cache key must come from the current request
        $locale = 'en';
        $slug = 'about';
        $host = 'example.test';

        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getId')->willReturn(42);
        $cmsStub->method('getPageTitle')->willReturn('About');
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn(new ArrayCollection(['block']));

        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findPublishedBySlug')->willReturn($cmsStub);

        $cmsFilterServiceStub = $this->createStub(CmsFilterService::class);
        $cmsFilterServiceStub->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        $request = Request::create('http://' . $host . '/about');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $capturedKeys = [];
        $cacheStub = $this->createStub(TagAwareCacheInterface::class);
        $cacheStub
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback, ?float $beta = null) use (&$capturedKeys): string {
                $capturedKeys[] = $key;
                return $callback(self::createCacheItem());
            });

        $twigStub = $this->createStub(Environment::class);
        $twigStub->method('render')->willReturn('rendered');

        $subject = new CmsService(
            twig: $twigStub,
            repo: $cmsRepoStub,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceStub,
            cache: $cacheStub,
            translator: new IdentityTranslator(),
            requestStack: $requestStack,
        );

        // Act
        $subject->handle($locale, $slug, new Response());

        // Assert: both the get() and the store() use the same key, prefixed with the page id
        static::assertCount(2, $capturedKeys);
        static::assertSame($capturedKeys[0], $capturedKeys[1]);
        static::assertStringStartsWith('cms_page.42.', $capturedKeys[0]);
    }

    public function testInvalidatePageInvalidatesPageTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_page_5']);

        $subject = $this->createServiceWithCache($cacheMock);

        // Act
        $subject->invalidatePage(5);
    }

    public function testInvalidateAllInvalidatesGlobalTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_page_all']);

        $subject = $this->createServiceWithCache($cacheMock);

        // Act
        $subject->invalidateAll();
    }

    public function testInvalidateMenuCachesInvalidatesMenuTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_menu']);

        $subject = $this->createServiceWithCache($cacheMock);

        // Act
        $subject->invalidateMenuCaches();
    }

    private function createServiceWithCache(TagAwareCacheInterface $cache): CmsService
    {
        return new CmsService(
            twig: $this->createStub(Environment::class),
            repo: $this->createStub(CmsRepository::class),
            eventFilterService: $this->createStub(EventFilterService::class),
            cmsFilterService: $this->createStub(CmsFilterService::class),
            cache: $cache,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
        );
    }

    private static function createCacheItem(): ItemInterface
    {
        return new class implements ItemInterface {
            private array $tags = [];

            public function getKey(): string
            {
                return '';
            }

            public function get(): mixed
            {
                return null;
            }

            public function isHit(): bool
            {
                return false;
            }

            public function set(mixed $value): static
            {
                return $this;
            }

            public function expiresAt(?\DateTimeInterface $expiration): static
            {
                return $this;
            }

            public function expiresAfter(int|\DateInterval|null $time): static
            {
                return $this;
            }

            public function tag(string|iterable $tags): static
            {
                $this->tags = is_string($tags) ? [$tags] : [...$tags];
                return $this;
            }

            public function getMetadata(): array
            {
                return [];
            }

            public function getTags(): array
            {
                return $this->tags;
            }
        };
    }
}
