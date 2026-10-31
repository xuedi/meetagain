<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Gallery;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\TrioCards;
use App\Entity\Cms;
use App\Entity\Image;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use App\Filter\Event\EventFilterResult;
use App\Filter\Event\EventFilterService;
use App\Repository\CmsRepository;
use App\Repository\ImageRepository;
use App\Service\Cms\CmsService;
use DateInterval;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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
        // Arrange
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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $result = $subject->getSites();

        // Assert
        static::assertSame($expectedSites, $result);
    }

    public function testHandleThrowsNotFoundWhenPageNotFound(): void
    {
        // Arrange
        $locale = 'en';
        $slug = 'non-existent-page';

        $cmsFilterServiceMock = $this->createMock(CmsFilterService::class);
        $cmsFilterServiceMock->expects($this->once())->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock->expects($this->once())->method('findPublishedBySlug')->with($slug, null)->willReturn(null);

        $twigMock = $this->createMock(Environment::class);
        $twigMock->expects($this->never())->method('render');

        // Arrange
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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $subject->handle($locale, $slug, new Response());
    }

    public function testHandleReturns204WhenPageHasNoContentInRequestedLanguage(): void
    {
        // Arrange
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

        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static fn(string $key, callable $callback): ?string => $callback(self::createCacheItem()));

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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $response = $subject->handle($locale, $slug, new Response());

        // Assert
        static::assertSame($expectedContent, $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @param list<string> $expectedTags
     */
    #[DataProvider('cachedPageTagsProvider')]
    public function testHandleReturns200AndTagsTheCachedBody(ArrayCollection $blocks, array $expectedTags): void
    {
        // Arrange
        $locale = 'en';
        $slug = 'existing-page';
        $pageTitle = 'Page Title';
        $expectedContent = 'rendered page content';

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
                    static::assertSame(['blocks' => $blocks, 'images' => []], $context);
                    return $renderedBody;
                }
                static::assertSame('cms/index.html.twig', $name);
                static::assertSame(['title' => $pageTitle, 'body' => $renderedBody], $context);
                return $expectedContent;
            });

        // Arrange
        $capturedKey = null;
        $capturedTags = null;
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback) use (&$capturedKey, &$capturedTags): ?string {
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

                    public function expiresAt(?DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(int|DateInterval|null $time): static
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
                $capturedTags = $item->getTags();
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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $response = $subject->handle($locale, $slug, new Response());

        // Assert
        static::assertSame($expectedContent, $response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotNull($capturedKey);
        static::assertStringStartsWith('cms_page.123.', $capturedKey);
        static::assertSame($expectedTags, $capturedTags);
    }

    public static function cachedPageTagsProvider(): Generator
    {
        yield 'a page without an event teaser' => [
            new ArrayCollection([self::textBlock(), self::textBlock()]),
            ['cms_page_123', 'cms_page_all'],
        ];
        yield 'a page with an event teaser' => [
            new ArrayCollection([self::textBlock(), EventTeaser::fromJson(['headline' => 'Next up', 'text' => ''])]),
            ['cms_page_123', 'cms_page_all', 'cms_event_teaser'],
        ];
        yield 'a page showing images tags each image once' => [
            new ArrayCollection([
                Text::fromJson(['content' => 'Body'], self::imageWithId(4)),
                Gallery::fromJson(['title' => '', 'images' => [['id' => 5, 'hash' => 'a'], ['id' => 4, 'hash' => 'b']]]),
            ]),
            ['cms_page_123', 'cms_page_all', 'cms_image_5', 'cms_image_4'],
        ];
    }

    public function testInvalidateEventTeasersInvalidatesTheTeaserTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_event_teaser']);

        $subject = $this->createServiceWithCache($cacheMock);

        // Act
        $subject->invalidateEventTeasers();
    }

    public function testInvalidateImageInvalidatesTheImageTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_image_7']);

        $subject = $this->createServiceWithCache($cacheMock);

        // Act
        $subject->invalidateImage(7);
    }

    public function testHandleUsesDefaultTitleWhenPageTitleIsNull(): void
    {
        // Arrange
        $locale = 'en';
        $slug = 'page-without-title';
        $expectedContent = 'rendered page content';
        $blocks = new ArrayCollection([self::textBlock()]);

        // Arrange
        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn($blocks);
        $cmsStub->method('getPageTitle')->willReturn(null);
        $cmsStub->method('getId')->willReturn(456);

        // Arrange
        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findPublishedBySlug')->willReturn($cmsStub);

        // Arrange
        $cmsFilterServiceStub = $this->createStub(CmsFilterService::class);
        $cmsFilterServiceStub->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        // Arrange
        $renderedBody = '<inner-body/>';
        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $name, array $context) use ($blocks, $renderedBody, $expectedContent): string {
                if ($name === 'cms/_blocks.html.twig') {
                    static::assertSame(['blocks' => $blocks, 'images' => []], $context);
                    return $renderedBody;
                }
                static::assertSame('cms/index.html.twig', $name);
                static::assertSame(['title' => 'cms.page_no_title_fallback', 'body' => $renderedBody], $context);
                return $expectedContent;
            });

        $cacheStub = $this->createStub(TagAwareCacheInterface::class);
        $cacheStub->method('get')->willReturnCallback(static fn(string $key, callable $callback, ?float $beta = null): string => $callback(
            self::createCacheItem(),
        ));

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoStub,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceStub,
            cache: $cacheStub,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $response = $subject->handle($locale, $slug, new Response());

        // Assert
        static::assertSame($expectedContent, $response->getContent());
    }

    public function testHandleReturnsCachedBodyWithoutFetchingBlocksOnCacheHit(): void
    {
        // Arrange
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

        // Arrange
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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $response = $subject->handle($locale, $slug, new Response());

        // Assert
        static::assertSame($outerHtml, $response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleCacheKeyIncludesHostFromCurrentRequest(): void
    {
        // Arrange
        $locale = 'en';
        $slug = 'about';
        $host = 'example.test';

        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getId')->willReturn(42);
        $cmsStub->method('getPageTitle')->willReturn('About');
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn(new ArrayCollection([self::textBlock()]));

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
            ->willReturnCallback(static function (string $key, callable $callback) use (&$capturedKeys): ?string {
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
            imageRepo: $this->createStub(ImageRepository::class),
        );

        // Act
        $subject->handle($locale, $slug, new Response());

        // Assert
        static::assertCount(1, $capturedKeys);
        static::assertStringStartsWith('cms_page.42.', $capturedKeys[0]);
    }

    public function testHandlePassesTheListedBlockImagesToTheBlockTemplates(): void
    {
        // Arrange
        $blocks = new ArrayCollection([
            self::textBlock(),
            Gallery::fromJson(['title' => '', 'images' => [['id' => 5, 'hash' => 'a'], ['id' => 6, 'hash' => 'b']]]),
            TrioCards::fromJson(['headline' => '', 'cards' => [['image' => ['id' => 7, 'hash' => 'c']], [], []]]),
        ]);
        $images = [5 => $this->createStub(Image::class), 7 => $this->createStub(Image::class)];

        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getId')->willReturn(9);
        $cmsStub->method('getPageTitle')->willReturn('Gallery');
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn($blocks);

        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findPublishedBySlug')->willReturn($cmsStub);

        $cmsFilterServiceStub = $this->createStub(CmsFilterService::class);
        $cmsFilterServiceStub->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());

        $eventFilterServiceStub = $this->createStub(EventFilterService::class);
        $eventFilterServiceStub->method('getEventIdFilter')->willReturn(new EventFilterResult(null, false));

        $cacheStub = $this->createStub(TagAwareCacheInterface::class);
        $cacheStub->method('get')->willReturnCallback(static fn(string $key, callable $callback): ?string => $callback(self::createCacheItem()));

        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock->expects($this->once())->method('findByIdsIndexed')->with([5, 6, 7])->willReturn($images);

        $capturedContext = null;
        $twigStub = $this->createStub(Environment::class);
        $twigStub
            ->method('render')
            ->willReturnCallback(static function (string $name, array $context) use (&$capturedContext): string {
                if ($name === 'cms/_blocks.html.twig') {
                    $capturedContext = $context;
                }
                return 'rendered';
            });

        $subject = new CmsService(
            twig: $twigStub,
            repo: $cmsRepoStub,
            eventFilterService: $eventFilterServiceStub,
            cmsFilterService: $cmsFilterServiceStub,
            cache: $cacheStub,
            translator: new IdentityTranslator(),
            requestStack: $this->createStub(RequestStack::class),
            imageRepo: $imageRepoMock,
        );

        // Act
        $subject->handle('en', 'gallery', new Response());

        // Assert
        static::assertSame(['blocks' => $blocks, 'images' => $images], $capturedContext);
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
            imageRepo: $this->createStub(ImageRepository::class),
        );
    }

    private static function imageWithId(int $id): Image
    {
        $image = new Image();
        new ReflectionProperty(Image::class, 'id')->setValue($image, $id);

        return $image;
    }

    private static function textBlock(): Text
    {
        return Text::fromJson(['content' => 'Body']);
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

            public function expiresAt(?DateTimeInterface $expiration): static
            {
                return $this;
            }

            public function expiresAfter(int|DateInterval|null $time): static
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
