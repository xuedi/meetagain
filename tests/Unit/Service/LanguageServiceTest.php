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
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);
    }

    public function testGetEnabledCodesUsesCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

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
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

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
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        $this->appCache->expects($this->once())->method('delete')->with('language.enabled_codes');

        $this->service->invalidateCache();
    }

    public function testInvalidateCacheHandlesException(): void
    {
        $this->appCache->method('delete')->willThrowException(new class extends Exception implements InvalidArgumentException {});

        $this->service->invalidateCache();
        static::assertTrue(true);
    }

    public function testGetLocaleRegexPattern(): void
    {
        $this->appCache->method('get')->willReturn(['en', 'de']);
        static::assertSame('en|de', $this->service->getLocaleRegexPattern());

        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);
        $this->appCache->method('get')->willReturn([]);
        static::assertSame('en', $this->service->getLocaleRegexPattern());
    }

    public function testGetAllLanguagesQueriesRepoEachCall(): void
    {
        $langEn = new Language();
        $this->languageRepo->method('findAllOrdered')->willReturn([$langEn]);

        // Doctrine entities are not cached (proxy associations break across
        // serialize/unserialize); the service is expected to delegate to the
        // repository directly.
        static::assertEquals([$langEn], $this->service->getAllLanguages());
        static::assertEquals([$langEn], $this->service->getAllLanguages());
    }

    public function testGetEnabledLanguagesQueriesRepoEachCall(): void
    {
        $langEn = new Language();
        $this->languageRepo->method('findEnabledOrdered')->willReturn([$langEn]);

        static::assertEquals([$langEn], $this->service->getEnabledLanguages());
        static::assertEquals([$langEn], $this->service->getEnabledLanguages());
    }

    public function testFindByCode(): void
    {
        $lang = new Language();
        $this->languageRepo->method('findByCode')->willReturn($lang);

        static::assertSame($lang, $this->service->findByCode('en'));
    }

    public function testGetFilteredEnabledCodesReturnsAllWhenNoActiveFilter(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de', 'zh'], $result);
    }

    public function testGetFilteredEnabledCodesReturnsIntersectionWithFilter(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['en', 'de'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenFilterReturnsEmptyResult(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::emptyResult());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenFilterCodesAreNull(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(null, true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetFilteredEnabledCodesFallbackWhenIntersectionIsEmpty(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['fr'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetAdminFilteredEnabledCodesReturnsAllWhenNoActiveFilter(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de', 'zh'], $result);
    }

    public function testGetAdminFilteredEnabledCodesReturnsIntersection(): void
    {
        // Arrange
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
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::emptyResult());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

    public function testGetAdminFilteredEnabledCodesFallbackWhenIntersectionEmpty(): void
    {
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'de']);
        $this->adminLanguageFilterService = $this->createStub(AdminLanguageFilterService::class);
        $this->adminLanguageFilterService->method('getLanguageCodeFilter')->willReturn(new LanguageFilterResult(['fr'], true));
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAdminFilteredEnabledCodes();

        // Assert
        static::assertEquals(['en', 'de'], $result);
    }

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
        // Arrange
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->appCache->method('get')->willReturnCallback(fn($key, $callback) => $callback($this->createStub(ItemInterface::class)));
        $this->languageRepo->method('getEnabledCodes')->willReturn(['en', 'zh']);
        $this->languageFilterService = $this->createStub(LanguageFilterService::class);
        $this->languageFilterService->method('getLanguageCodeFilter')->willReturn(LanguageFilterResult::noFilter());
        $this->service = new LanguageService($this->languageRepo, $this->appCache, $this->languageFilterService, $this->adminLanguageFilterService);

        // Act
        $result = $this->service->getAltLangList('zh', '/zh/events');

        // Assert
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

        // Act & Assert
        static::assertSame('/some/path', $this->service->replaceUriLanguageCode('/some/path', 'zh'));
    }
}
