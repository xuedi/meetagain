<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Filter\Language\AlternateLinkFilterInterface;
use App\Publisher\AlternateLinks\AlternateLinkProviderInterface;
use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\UrlOwnerService;
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
    private Stub&UrlOwnerProviderInterface $urlOwnerProviderStub;
    private LanguageRuntime $subject;

    protected function setUp(): void
    {
        $this->languageServiceStub = $this->createStub(LanguageService::class);
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->routerStub = $this->createStub(RouterInterface::class);
        $this->urlOwnerProviderStub = $this->createStub(UrlOwnerProviderInterface::class);
        $configServiceStub = $this->createStub(ConfigService::class);
        $configServiceStub->method('getHost')->willReturn('https://meetagain.local/');
        $urlOwnerService = new UrlOwnerService($configServiceStub, [$this->urlOwnerProviderStub]);
        $this->subject = new LanguageRuntime($this->languageServiceStub, $this->requestStackStub, $this->routerStub, $urlOwnerService);
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
        $this->expectExceptionMessageIsOrContains('Could not get current locale');

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

    public function testGetHreflangLanguageCodesUsesTheOwnerHost(): void
    {
        // Arrange
        $request = new Request();
        $request->attributes->set('_route', 'app_frontpage');
        $request->attributes->set('_route_params', ['x' => 1]);
        $request->server->set('REQUEST_URI', '/de/');
        $request->setLocale('de');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);
        $this->languageServiceStub->method('getAltLangList')->willReturn(['en' => '/en/']);
        $this->languageServiceStub->method('replaceUriLanguageCode')->willReturn('/de/');
        $this->urlOwnerProviderStub->method('getOwnerHost')->willReturn('https://club.example/');

        // Act
        $result = $this->subject->getHreflangLanguageCodes();

        // Assert
        static::assertSame(['en' => 'https://club.example/en/', 'de' => 'https://club.example/de/'], $result);
    }

    public function testAProvidedXDefaultSurvivesANarrowingFilter(): void
    {
        // Arrange
        $request = new Request();
        $request->server->set('REQUEST_URI', '/');
        $request->setLocale('en');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);
        $this->languageServiceStub->method('getAltLangList')->willReturn(['en' => '/en/', 'de' => '/de/', 'zh' => '/zh/']);
        $this->languageServiceStub->method('replaceUriLanguageCode')->willReturn('/en/');
        $provider = $this->createStub(AlternateLinkProviderInterface::class);
        $provider
            ->method('getAlternateLinks')
            ->willReturn([
                'en' => 'https://meetagain.local/',
                'de' => 'https://meetagain.local/de/',
                'zh' => 'https://meetagain.local/zh/',
                AlternateLinkProviderInterface::X_DEFAULT => 'https://meetagain.local/',
            ]);
        $filter = $this->createStub(AlternateLinkFilterInterface::class);
        $filter->method('getAllowedAlternateLocaleCodes')->willReturn(['en', 'de']);
        $configServiceStub = $this->createStub(ConfigService::class);
        $configServiceStub->method('getHost')->willReturn('https://meetagain.local/');
        $subject = new LanguageRuntime(
            $this->languageServiceStub,
            $this->requestStackStub,
            $this->routerStub,
            new UrlOwnerService($configServiceStub, []),
            [$filter],
            [$provider],
        );

        // Act
        $result = $subject->getHreflangLanguageCodes();

        // Assert
        static::assertSame(
            [
                'en' => 'https://meetagain.local/',
                'de' => 'https://meetagain.local/de/',
                AlternateLinkProviderInterface::X_DEFAULT => 'https://meetagain.local/',
            ],
            $result,
        );
    }
}
