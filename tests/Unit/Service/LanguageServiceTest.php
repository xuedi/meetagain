<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Service\LanguageService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class LanguageServiceTest extends TestCase
{
    private LanguageRepository|Stub $languageRepo;
    private TagAwareCacheInterface|Stub $appCache;
    private LanguageService $service;

    protected function setUp(): void
    {
        $this->languageRepo = $this->createStub(LanguageRepository::class);
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);
    }

    public function testGetEnabledCodesUsesCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);

        $this->appCache->expects($this->once())
            ->method('get')
            ->with('language.enabled_codes')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $this->languageRepo->method('getEnabledCodes')
            ->willReturn(['en', 'de']);

        $this->assertEquals(['en', 'de'], $this->service->getEnabledCodes());
    }

    public function testGetEnabledCodesFallbackOnCacheError(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);

        $this->appCache->expects($this->once())
            ->method('get')
            ->willThrowException(
                new class extends \Exception implements InvalidArgumentException {
                }
            );

        $this->languageRepo->method('getEnabledCodes')
            ->willReturn(['en']);

        $this->assertEquals(['en'], $this->service->getEnabledCodes());
    }

    public function testIsValidCode(): void
    {
        $this->appCache->method('get')->willReturn(['en', 'de']);

        $this->assertTrue($this->service->isValidCode('en'));
        $this->assertFalse($this->service->isValidCode('fr'));
    }

    public function testInvalidateCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);

        $this->appCache->expects($this->exactly(2))
            ->method('delete')
            ->with(
                $this->logicalOr(
                    $this->equalTo('language.enabled_codes'),
                    $this->equalTo('language.all_languages')
                )
            );

        $this->service->invalidateCache();
    }

    public function testInvalidateCacheHandlesException(): void
    {
        $this->appCache->method('delete')
            ->willThrowException(
                new class extends \Exception implements InvalidArgumentException {
                }
            );

        $this->service->invalidateCache();
        $this->assertTrue(true); // Should not throw
    }

    public function testGetLocaleRegexPattern(): void
    {
        $this->appCache->method('get')->willReturn(['en', 'de']);
        $this->assertEquals('en|de', $this->service->getLocaleRegexPattern());

        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);
        $this->appCache->method('get')->willReturn([]);
        $this->assertEquals('en', $this->service->getLocaleRegexPattern());
    }

    public function testGetAllLanguagesUsesCache(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);

        $langEn = new Language();
        $this->appCache->expects($this->once())
            ->method('get')
            ->with('language.all_languages')
            ->willReturnCallback(function ($key, $callback) use ($langEn) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $this->languageRepo->method('findAllOrdered')
            ->willReturn([$langEn]);

        $this->assertEquals([$langEn], $this->service->getAllLanguages());
    }

    public function testGetAllLanguagesFallbackOnCacheError(): void
    {
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new LanguageService($this->languageRepo, $this->appCache);

        $this->appCache->expects($this->once())
            ->method('get')
            ->willThrowException(
                new class extends \Exception implements InvalidArgumentException {
                }
            );

        $this->languageRepo->method('findAllOrdered')
            ->willReturn([]);

        $this->assertEquals([], $this->service->getAllLanguages());
    }

    public function testFindByCode(): void
    {
        $lang = new Language();
        $this->languageRepo->method('findByCode')
            ->with('en')
            ->willReturn($lang);

        $this->assertSame($lang, $this->service->findByCode('en'));
    }
}
