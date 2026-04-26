<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enum\CmsBlock\CmsBlockType;
use App\Repository\CmsBlockRepository;
use App\Service\Cms\CmsPageCacheService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CmsPageCacheServiceTest extends TestCase
{
    private function createService(TagAwareCacheInterface $cache, CmsBlockRepository $blockRepo): CmsPageCacheService
    {
        return new CmsPageCacheService(cache: $cache, blockRepo: $blockRepo);
    }

    // ---- computeEventFilterFingerprint ----

    #[DataProvider('computeEventFilterFingerprintProvider')]
    public function testComputeEventFilterFingerprint(?array $eventIds, string $expected): void
    {
        // Arrange
        $service = $this->createService(
            $this->createStub(TagAwareCacheInterface::class),
            $this->createStub(CmsBlockRepository::class),
        );

        // Act
        $result = $service->computeEventFilterFingerprint($eventIds);

        // Assert
        static::assertSame($expected, $result);
    }

    public static function computeEventFilterFingerprintProvider(): iterable
    {
        yield 'null eventIds returns global' => [null, 'global'];
        yield 'empty array returns md5 of empty string' => [[], md5('')];
        yield 'single id returns md5 of that id' => [[42], md5('42')];
        yield 'ids are sorted before fingerprinting' => [[3, 1, 2], md5('1,2,3')];
        yield 'different order same ids → same result' => [[5, 1], md5('1,5')];
    }

    // ---- get(): cache hit ----

    public function testGetReturnsCachedHtmlOnCacheHit(): void
    {
        // Arrange: cache returns the stored HTML without invoking the callback
        $cacheMock = $this->createStub(TagAwareCacheInterface::class);
        $cacheMock
            ->method('get')
            ->willReturnCallback(static fn(string $key, callable $callback): string => '<p>cached</p>');

        $service = $this->createService($cacheMock, $this->createStub(CmsBlockRepository::class));

        // Act
        $result = $service->get(1, 'en', null);

        // Assert: cached HTML is returned (not null)
        static::assertSame('<p>cached</p>', $result);
    }

    // ---- get(): cache miss ----

    public function testGetReturnsNullOnCacheMiss(): void
    {
        // Arrange: cache miss — callback IS invoked (sentinel empty string written)
        $cacheMock = $this->createStub(TagAwareCacheInterface::class);
        $cacheMock
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback): string {
                $item = new class implements ItemInterface {
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
                        return $this;
                    }

                    public function getMetadata(): array
                    {
                        return [];
                    }
                };

                return $callback($item);
            });

        $service = $this->createService($cacheMock, $this->createStub(CmsBlockRepository::class));

        // Act
        $result = $service->get(1, 'en', null);

        // Assert: miss marker → null returned
        static::assertNull($result);
    }

    // ---- store() ----

    public function testStoreInvokesCallbackWithCorrectTag(): void
    {
        // Arrange
        $capturedTag = null;
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->with($this->stringContains('cms_page.7.'), $this->isCallable(), \INF)
            ->willReturnCallback(static function (string $key, callable $callback, float $beta) use (
                &$capturedTag,
            ): string {
                $item = new class implements ItemInterface {
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

                $result = $callback($item);
                $capturedTag = $item->getTags();

                return $result;
            });

        $service = $this->createService($cacheMock, $this->createStub(CmsBlockRepository::class));

        // Act
        $service->store(7, 'en', null, '<h1>Hello</h1>');

        // Assert: page tag plus the global cms_page_all tag (used by invalidateAll)
        static::assertSame(['cms_page_7', 'cms_page_all'], $capturedTag);
    }

    // ---- invalidatePage() ----

    public function testInvalidatePageCallsInvalidateTagsWithPageTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_page_5']);

        $service = $this->createService($cacheMock, $this->createStub(CmsBlockRepository::class));

        // Act
        $service->invalidatePage(5);
    }

    // ---- invalidateMenuCaches() ----

    public function testInvalidateMenuCachesCallsInvalidateTagsWithMenuTag(): void
    {
        // Arrange
        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('invalidateTags')->with(['cms_menu']);

        $service = $this->createService($cacheMock, $this->createStub(CmsBlockRepository::class));

        // Act
        $service->invalidateMenuCaches();
    }

    // ---- findEventTeaserPageIds() ----

    public function testFindEventTeaserPageIdsDelegatesToBlockRepo(): void
    {
        // Arrange
        $repoMock = $this->createMock(CmsBlockRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findPageIdsWithType')
            ->with(CmsBlockType::EventTeaser)
            ->willReturn([3, 7, 12]);

        $service = $this->createService($this->createStub(TagAwareCacheInterface::class), $repoMock);

        // Act
        $result = $service->findEventTeaserPageIds();

        // Assert
        static::assertSame([3, 7, 12], $result);
    }
}
