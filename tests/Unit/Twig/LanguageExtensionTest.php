<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Twig\LanguageExtension;
use App\Twig\MetaDescriptionProviderInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class LanguageExtensionTest extends TestCase
{
    private Stub&LanguageService $languageServiceStub;
    private Stub&RequestStack $requestStackStub;
    private Stub&RouterInterface $routerStub;
    private Stub&ConfigService $configServiceStub;
    private LanguageExtension $subject;

    protected function setUp(): void
    {
        $this->languageServiceStub = $this->createStub(LanguageService::class);
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->routerStub = $this->createStub(RouterInterface::class);
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->subject = new LanguageExtension(
            $this->languageServiceStub,
            $this->requestStackStub,
            $this->routerStub,
            $this->configServiceStub,
        );
    }

    public function testGetGlobalsReturnsEnabledLocales(): void
    {
        $this->languageServiceStub->method('getFilteredEnabledCodes')->willReturn(['en', 'de', 'zh']);

        $globals = $this->subject->getGlobals();

        static::assertArrayHasKey('enabled_locales', $globals);
        static::assertSame(['en', 'de', 'zh'], $globals['enabled_locales']);
    }

    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->subject->getFunctions();

        static::assertCount(9, $functions);

        $functionNames = array_map(static fn($f) => $f->getName(), $functions);
        static::assertContains('get_enabled_locales', $functionNames);
        static::assertContains('get_all_languages', $functionNames);
        static::assertContains('current_locale', $functionNames);
        static::assertContains('get_alternative_languages', $functionNames);
        static::assertContains('get_language_codes', $functionNames);
        static::assertContains('get_admin_language_codes', $functionNames);
        static::assertContains('route_exists', $functionNames);
        static::assertContains('get_canonical_url', $functionNames);
        static::assertContains('get_meta_description', $functionNames);
    }

    public function testGetCurrentLocaleReturnsRequestLocale(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getLocale')->willReturn('de');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        static::assertSame('de', $this->subject->getCurrentLocale());
    }

    public function testGetCurrentLocaleThrowsWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not get current locale');

        $this->subject->getCurrentLocale();
    }

    public function testGetAlternativeLanguageCodesReturnsAbsoluteUrls(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/en/events');
        $request->method('getLocale')->willReturn('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);
        $this->configServiceStub->method('getHost')->willReturn('https://meetagain.local');

        $this->languageServiceStub
            ->method('getAltLangList')
            ->willReturn(['de' => '/de/events', 'zh' => '/zh/events']);

        $result = $this->subject->getAlternativeLanguageCodes();

        static::assertSame([
            'de' => 'https://meetagain.local/de/events',
            'zh' => 'https://meetagain.local/zh/events',
        ], $result);
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyForProfiler(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/_profiler/123');
        $request->method('getLocale')->willReturn('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $result = $this->subject->getAlternativeLanguageCodes();

        static::assertSame([], $result);
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);

        $result = $this->subject->getAlternativeLanguageCodes();

        static::assertSame([], $result);
    }

    public function testRouteExistsReturnsTrueWhenRouteCanBeGenerated(): void
    {
        // Arrange
        $this->routerStub
            ->method('generate')
            ->willReturn('/some/path');

        // Act
        $result = $this->subject->routeExists('some_route');

        // Assert
        static::assertTrue($result);
    }

    public function testRouteExistsReturnsFalseWhenRouteNotFound(): void
    {
        // Arrange
        $this->routerStub->method('generate')->willThrowException(new RouteNotFoundException());

        // Act
        $result = $this->subject->routeExists('nonexistent_route');

        // Assert
        static::assertFalse($result);
    }

    public function testRouteExistsReturnsTrueWhenRouteExistsButRequiresParams(): void
    {
        // Arrange
        $this->routerStub->method('generate')->willThrowException(new MissingMandatoryParametersException('id'));

        // Act
        $result = $this->subject->routeExists('parameterized_route');

        // Assert
        static::assertTrue($result);
    }

    public function testGetCanonicalUrlCombinesConfigHostWithRequestUri(): void
    {
        // Arrange
        $this->configServiceStub->method('getHost')->willReturn('https://meetagain.local');
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/en/events');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        // Act
        $result = $this->subject->getCanonicalUrl();

        // Assert
        static::assertSame('https://meetagain.local/en/events', $result);
    }

    public function testGetMetaDescriptionReturnsProviderValueWhenAvailable(): void
    {
        // Arrange
        $provider = $this->createStub(MetaDescriptionProviderInterface::class);
        $provider->method('getMetaDescription')->willReturn('Weiqi club upcoming events');

        $subject = new LanguageExtension(
            $this->languageServiceStub,
            $this->requestStackStub,
            $this->routerStub,
            $this->configServiceStub,
            [$provider],
        );

        // Act
        $result = $subject->getMetaDescription('events');

        // Assert
        static::assertSame('Weiqi club upcoming events', $result);
    }

    public function testGetMetaDescriptionFallsBackToSystemConfigWhenNoProviderValue(): void
    {
        // Arrange
        $this->configServiceStub
            ->method('getSeoDescription')
            ->willReturn('System events description');

        // Act
        $result = $this->subject->getMetaDescription('events');

        // Assert
        static::assertSame('System events description', $result);
    }

    public function testGetMetaDescriptionFallsBackToHardcodedWhenNothingConfigured(): void
    {
        // Arrange
        $this->configServiceStub->method('getSeoDescription')->willReturn('');

        // Act
        $result = $this->subject->getMetaDescription('members');

        // Assert
        static::assertSame('Meet the members of this community.', $result);
    }

    public function testGetCanonicalUrlStripsTrailingSlashFromHost(): void
    {
        // Arrange
        $this->configServiceStub->method('getHost')->willReturn('https://meetagain.local/');
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/en/members');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        // Act
        $result = $this->subject->getCanonicalUrl();

        // Assert
        static::assertSame('https://meetagain.local/en/members', $result);
    }
}
