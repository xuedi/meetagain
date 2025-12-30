<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\LanguageService;
use App\Service\TranslationService;
use App\Twig\LanguageExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LanguageExtensionTest extends TestCase
{
    private Stub&LanguageService $languageServiceStub;
    private Stub&TranslationService $translationServiceStub;
    private Stub&RequestStack $requestStackStub;
    private LanguageExtension $subject;

    protected function setUp(): void
    {
        $this->languageServiceStub = $this->createStub(LanguageService::class);
        $this->translationServiceStub = $this->createStub(TranslationService::class);
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->subject = new LanguageExtension(
            $this->languageServiceStub,
            $this->translationServiceStub,
            $this->requestStackStub
        );
    }

    public function testGetGlobalsReturnsEnabledLocales(): void
    {
        $this->languageServiceStub->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);

        $globals = $this->subject->getGlobals();

        $this->assertArrayHasKey('enabled_locales', $globals);
        $this->assertSame(['en', 'de', 'zh'], $globals['enabled_locales']);
    }

    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->subject->getFunctions();

        $this->assertCount(5, $functions);

        $functionNames = array_map(fn($f) => $f->getName(), $functions);
        $this->assertContains('get_enabled_locales', $functionNames);
        $this->assertContains('get_all_languages', $functionNames);
        $this->assertContains('current_locale', $functionNames);
        $this->assertContains('get_alternative_languages', $functionNames);
        $this->assertContains('get_language_codes', $functionNames);
    }

    public function testGetCurrentLocaleReturnsRequestLocale(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getLocale')->willReturn('de');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $this->assertSame('de', $this->subject->getCurrentLocale());
    }

    public function testGetCurrentLocaleThrowsWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not get current locale');

        $this->subject->getCurrentLocale();
    }

    public function testGetAlternativeLanguageCodesReturnsListFromService(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/en/events');
        $request->method('getLocale')->willReturn('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $this->translationServiceStub
            ->method('getAltLangList')
            ->with('en', '/en/events')
            ->willReturn(['de' => '/de/events', 'zh' => '/zh/events']);

        $result = $this->subject->getAlternativeLanguageCodes();

        $this->assertSame(['de' => '/de/events', 'zh' => '/zh/events'], $result);
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyForProfiler(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/_profiler/123');
        $request->method('getLocale')->willReturn('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $result = $this->subject->getAlternativeLanguageCodes();

        $this->assertSame([], $result);
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);

        $result = $this->subject->getAlternativeLanguageCodes();

        $this->assertSame([], $result);
    }
}
