<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Language;
use App\Filter\Admin\Language\AdminLanguageFilterService;
use App\Filter\Language\LanguageFilterResult;
use App\Filter\Language\LanguageFilterService;
use App\Repository\LanguageRepository;
use App\Service\Config\LanguageService;
use Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class LanguageServiceTest extends TestCase
{
    private LanguageRepository|Stub $languageRepo;
    private TagAwareCacheInterface|Stub $appCache;
    private LanguageFilterService|Stub $languageFilterService;
    private AdminLanguageFilterService|Stub $adminLanguageFilterService;
    private LanguageService $service;

    protected function setUp(): void
    {
        $this->languageRepo = $this->createStub(LanguageRepository::class);
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService
            ->method('getLanguageCodeFilter')
            ->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );
    }

    public function testGetEnabledCodesUsesCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );

        $this->appCache
            ->expects($this->once())
            ->method('get')
            ->with('language.enabled_codes')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);

        static::assertEquals(['en', 'de'], $this->service->getEnabledCodes());
    }

    public function testGetEnabledCodesFallbackOnCacheError(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );

        $this->appCache
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new class extends Exception implements InvalidArgumentException {});

        $this->languageRepo->method('getEnabledCodes')->willReturn(['en']);

        static::assertEquals(['en'], $this->service->getEnabledCodes());
    }

    public function testIsValidCode(): void
    {
        $this->appCache->method('get')->willReturn(['en', 'de']);

        static::assertTrue($this->service->isValidCode('en'));
        static::assertFalse($this->service->isValidCode('fr'));
    }

    public function testInvalidateCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );

        $this->appCache
            ->expects($this->exactly(3))
            ->method('delete')
            ->with(static::logicalOr(
                static::equalTo('language.enabled_codes'),
                static::equalTo('language.all_languages'),
                static::equalTo('language.enabled_languages'),
            ));

        $this->service->invalidateCache();
    }

    public function testInvalidateCacheHandlesException(): void
    {
        $this->appCache
            ->method('delete')
            ->willThrowException(new class extends Exception implements InvalidArgumentException {});

        $this->service->invalidateCache();
        static::assertTrue(true); // Should not throw
    }

    public function testGetLocaleRegexPattern(): void
    {
        $this->appCache->method('get')->willReturn(['en', 'de']);
        static::assertSame('en|de', $this->service->getLocaleRegexPattern());

        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );
        $this->appCache->method('get')->willReturn([]);
        static::assertSame('en', $this->service->getLocaleRegexPattern());
    }

    public function testGetAllLanguagesUsesCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );

        $langEn = new Language();
        $this->appCache
            ->expects($this->once())
            ->method('get')
            ->with('language.all_languages')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $this->languageRepo->method('findAllOrdered')->willReturn([$langEn]);

        static::assertEquals([$langEn], $this->service->getAllLanguages());
    }

    public function testGetAllLanguagesFallbackOnCacheError(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService(
            $this->languageRepo,
            $this->appCache,
            $this->languageFilterService,
            $this->adminLanguageFilterService,
        );

        $this->appCache
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new class extends Exception implements InvalidArgumentException {});

        $this->languageRepo->method('findAllOrdered')->willReturn([]);

        static::assertEquals([], $this->service->getAllLanguages());
    }

    public function testFindByCode(): void
    {
        $lang = new Language();
        $this->languageRepo
            ->method('findByCode')
            ->willReturn($lang);

        static::assertSame($lang, $this->service->findByCode('en'));
    }

    // --- getFilteredEnabledCodes ---

    public function testGetFilteredEnabledCodesReturnsAllWhenNoActiveFilter(): void
    {
        // Arrange - Filter returns noFilter() (no active filter)
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert - All enabled codes returned when no filter active
        static::assertEquals(['en', 'de', 'zh'], $result);
    }

    public function testGetFilteredEnabledCodesReturnsIntersectionWithFilter(): void
    {
        // Arrange - Filter restricts to ['en', 'de'], enabled codes are ['en', 'de', 'zh']
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['en', 'de'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert - Only codes in both filter result and enabled set are returned
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenFilterReturnsEmptyResult(): void
    {
        // Arrange - Filter returns emptyResult() (hasActiveFilter=true but codes=[])
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::emptyResult());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert - Safety fallback: never show zero language tabs
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenFilterCodesAreNull(): void
    {
        // Arrange - Filter is active but returns null codes (hasActiveFilter=true, codes=null)
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(null, true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert - Safety fallback: null codes with active filter still returns all enabled
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenIntersectionIsEmpty(): void
    {
        // Arrange - Filter returns ['fr'] but enabled codes are ['en', 'de'] — no overlap
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['fr'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert - Safety fallback: empty intersection returns all enabled codes
        static::assertEquals(['en', 'de'], $result);
    }

    // --- getAdminFilteredEnabledCodes ---

    public function testGetAdminFilteredEnabledCodesReturnsAllWhenNoActiveFilter(): void
    {
        // Arrange - Admin filter returns noFilter()
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert - All enabled codes returned when no filter active
        static::assertEquals(['en', 'de', 'zh'], $result);
    }

    public function testGetAdminFilteredEnabledCodesReturnsIntersection(): void
    {
        // Arrange - Admin filter restricts to ['en'] only
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['en'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en'], $result);
    }

    public function testGetAdminFilteredEnabledCodesFallbackWhenEmptyResult(): void
    {
        // Arrange - Admin filter returns emptyResult()
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::emptyResult());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert - Safety fallback: empty result never breaks admin interface
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetAdminFilteredEnabledCodesFallbackWhenIntersectionEmpty(): void
    {
        // Arrange - Admin filter returns ['fr'] but enabled codes are ['en', 'de'] — no overlap
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['fr'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert - Safety fallback: empty intersection returns all enabled codes
        static::assertEquals(['en', 'de'], $result);
    }

    // --- getAltLangList ---

    public function testGetAltLangListReturnsAlternativesExcludingCurrentLocale(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAltLangList('en', '/en/events');

        // Assert
        static::assertArrayNotHasKey('en', $result);
        static::assertSame('/de/events', $result['de']);
        static::assertSame('/zh/events', $result['zh']);
    }

    public function testGetAltLangListWithZhLocaleReplacesCorrectly(): void
    {
        // Arrange — zh locale after DB rename: zh is a first-class code with no mapping needed
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAltLangList('zh', '/zh/events');

        // Assert — current locale excluded; en alternative URL uses en prefix
        static::assertArrayNotHasKey('zh', $result);
        static::assertSame('/en/events', $result['en']);
    }

    public function testGetAltLangListReturnsEmptyWhenOnlyOneLocale(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAltLangList('en', '/en/events');

        // Assert
        static::assertSame([], $result);
    }

    // --- replaceUriLanguageCode ---

    public function testReplaceUriLanguageCodeSwapsLocaleInPath(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act & Assert
        static::assertSame('/zh/events', $this->service->replaceUriLanguageCode('/en/events', 'zh'));
        static::assertSame('/en/events', $this->service->replaceUriLanguageCode('/zh/events', 'en'));
    }

    public function testReplaceUriLanguageCodeHandlesLocaleOnlyUri(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'zh']);
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act & Assert
        static::assertSame('/zh/', $this->service->replaceUriLanguageCode('/en/', 'zh'));
    }

    public function testReplaceUriLanguageCodeReturnsSameWhenNoLocalePrefix(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'zh']);
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act & Assert — URI without a known locale prefix is returned unchanged
        static::assertSame('/some/path', $this->service->replaceUriLanguageCode('/some/path', 'zh'));
    }
}
