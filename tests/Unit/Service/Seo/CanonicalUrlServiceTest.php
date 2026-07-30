<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Publisher\CanonicalUrl\CanonicalUrlProviderInterface;
use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Seo\CanonicalUrlService;
use App\Service\Seo\UrlOwnerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CanonicalUrlServiceTest extends TestCase
{
    private function makeService(
        string $host = 'https://example.com',
        iterable $providers = [],
        iterable $urlOwnerProviders = [],
    ): CanonicalUrlService {
        $configStub = $this->createStub(ConfigService::class);
        $configStub->method('getHost')->willReturn($host);

        return new CanonicalUrlService(
            urlOwnerService: new UrlOwnerService($configStub, $urlOwnerProviders),
            providers: $providers,
        );
    }

    public function testNoProvidersReturnsHostPlusRequestUri(): void
    {
        // Arrange
        $request = Request::create('/events?page=2');
        $service = $this->makeService('https://example.com');

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://example.com/events', $result);
    }

    public function testProviderReturnsNullFallsBackToDefault(): void
    {
        // Arrange
        $provider = $this->createStub(CanonicalUrlProviderInterface::class);
        $provider->method('getCanonicalUrl')->willReturn(null);

        $request = Request::create('/members');
        $service = $this->makeService('https://example.com', [$provider]);

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://example.com/members', $result);
    }

    public function testProviderReturnsUrlUsesIt(): void
    {
        // Arrange
        $provider = $this->createStub(CanonicalUrlProviderInterface::class);
        $provider->method('getCanonicalUrl')->willReturn('https://custom.example.org/page');

        $request = Request::create('/page');
        $service = $this->makeService('https://example.com', [$provider]);

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://custom.example.org/page', $result);
    }

    public function testMultipleProvidersFirstNullSecondReturnsUrl(): void
    {
        // Arrange
        $first = $this->createStub(CanonicalUrlProviderInterface::class);
        $first->method('getCanonicalUrl')->willReturn(null);

        $second = $this->createStub(CanonicalUrlProviderInterface::class);
        $second->method('getCanonicalUrl')->willReturn('https://second.example.com/page');

        $request = Request::create('/page');
        $service = $this->makeService('https://example.com', [$first, $second]);

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://second.example.com/page', $result);
    }

    public function testTrailingSlashInHostIsStripped(): void
    {
        // Arrange
        $request = Request::create('/path');
        $service = $this->makeService('https://example.com/');

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://example.com/path', $result);
    }

    public function testTheOwningHostReplacesTheConfiguredOneInTheDefault(): void
    {
        // Arrange
        $owner = $this->createStub(UrlOwnerProviderInterface::class);
        $owner
            ->method('getOwnerHost')
            ->willReturnCallback(static fn(string $route) => $route === 'app_dishes_dishlist' ? 'https://cinema.example.org' : null);

        $request = Request::create('/en/dishes');
        $request->attributes->set('_route', 'app_dishes_dishlist');
        $service = $this->makeService('https://example.com', [], [$owner]);

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://cinema.example.org/en/dishes', $result);
    }

    public function testRouteParametersReachTheOwnershipSeam(): void
    {
        // Arrange
        $owner = $this->createStub(UrlOwnerProviderInterface::class);
        $owner
            ->method('getOwnerHost')
            ->willReturnCallback(
                static fn(string $route, array $parameters) => ($parameters['id'] ?? null) === 17 ? 'https://dragon.example.org' : null,
            );

        $request = Request::create('/en/event/17');
        $request->attributes->set('_route', 'app_event_details');
        $request->attributes->set('_route_params', ['_locale' => 'en', 'id' => 17]);
        $service = $this->makeService('https://example.com', [], [$owner]);

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://dragon.example.org/en/event/17', $result);
    }
}
