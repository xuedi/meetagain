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
            ->expects($this->exactly(2))
            ->method('delete')
            ->with(static::logicalOr(
                static::equalTo('language.enabled_codes'),
                static::equalTo('language.all_languages'),
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
            ->with('en')
            ->willReturn($lang);

        static::assertSame($lang, $this->service->findByCode('en'));
    }
}
