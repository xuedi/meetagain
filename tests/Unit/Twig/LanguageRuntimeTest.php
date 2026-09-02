<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Twig\LanguageRuntime;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class LanguageRuntimeTest extends TestCase
{
    private Stub&LanguageService $languageServiceStub;
    private Stub&RequestStack $requestStackStub;
    private Stub&RouterInterface $routerStub;
    private Stub&ConfigService $configServiceStub;
    private LanguageRuntime $subject;

    protected function setUp(): void
    {
        $this->languageServiceStub = $this->createStub(LanguageService::class);
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->routerStub = $this->createStub(RouterInterface::class);
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->subject = new LanguageRuntime(
            $this->languageServiceStub,
            $this->requestStackStub,
            $this->routerStub,
            $this->configServiceStub,
        );
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

    public function testGetAlternativeLanguageCodesReturnsAbsoluteUrlsOnCurrentHost(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/en/events');
        $request->method('getLocale')->willReturn('en');
        $request->method('getSchemeAndHttpHost')->willReturn('https://meetagain.local');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $this->languageServiceStub->method('getAltLangList')->willReturn(['de' => '/de/events', 'zh' => '/zh/events']);

        $result = $this->subject->getLanguageSwitcherOptions();

        static::assertSame(
            [
                'de' => 'https://meetagain.local/de/events',
                'zh' => 'https://meetagain.local/zh/events',
            ],
            $result,
        );
    }

    public function testGetAlternativeLanguageCodesStaysOnSameHost(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/zh/events');
        $request->method('getLocale')->willReturn('zh');
        $request->method('getSchemeAndHttpHost')->willReturn('https://weiqi.example.org');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $this->languageServiceStub->method('getAltLangList')->willReturn(['en' => '/en/events', 'de' => '/de/events']);

        $result = $this->subject->getLanguageSwitcherOptions();

        static::assertSame(
            [
                'en' => 'https://weiqi.example.org/en/events',
                'de' => 'https://weiqi.example.org/de/events',
            ],
            $result,
        );
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyForProfiler(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getRequestUri')->willReturn('/_profiler/123');
        $request->method('getLocale')->willReturn('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $result = $this->subject->getLanguageSwitcherOptions();

        static::assertSame([], $result);
    }

    public function testGetAlternativeLanguageCodesReturnsEmptyWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);

        $result = $this->subject->getLanguageSwitcherOptions();

        static::assertSame([], $result);
    }

    public function testRouteExistsReturnsTrueWhenRouteCanBeGenerated(): void
    {
        // Arrange
        $this->routerStub->method('generate')->willReturn('/some/path');

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

}
