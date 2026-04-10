<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Filter\CanonicalUrlProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Seo\CanonicalUrlService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CanonicalUrlServiceTest extends TestCase
{
    private function makeService(string $host = 'https://example.com', iterable $providers = []): CanonicalUrlService
    {
        $configStub = $this->createStub(ConfigService::class);
        $configStub->method('getHost')->willReturn($host);

        return new CanonicalUrlService(configService: $configStub, providers: $providers);
    }

    public function testNoProvidersReturnsHostPlusRequestUri(): void
    {
        // Arrange
        $request = Request::create('/events?page=2');
        $service = $this->makeService('https://example.com');

        // Act
        $result = $service->getCanonicalUrl($request);

        // Assert
        static::assertSame('https://example.com/events?page=2', $result);
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

        // Assert: no double slash between host and path
        static::assertSame('https://example.com/path', $result);
    }
}
