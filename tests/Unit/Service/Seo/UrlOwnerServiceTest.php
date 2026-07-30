<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Seo\UrlOwnerService;
use PHPUnit\Framework\TestCase;

class UrlOwnerServiceTest extends TestCase
{
    public function testFallsBackToTheConfiguredHostWithNoProviders(): void
    {
        // Arrange
        $service = new UrlOwnerService($this->makeConfig('https://meetagain.org'), []);

        // Act
        $ownerHost = $service->getOwnerHost('app_dishes_dishlist');

        // Assert
        self::assertSame('https://meetagain.org', $ownerHost);
    }

    public function testStripsATrailingSlashFromTheConfiguredHost(): void
    {
        // Arrange
        $service = new UrlOwnerService($this->makeConfig('https://meetagain.org/'), []);

        // Act
        $ownerHost = $service->getOwnerHost('app_dishes_dishlist');

        // Assert
        self::assertSame('https://meetagain.org', $ownerHost);
    }

    public function testTakesTheFirstProviderAnswer(): void
    {
        // Arrange
        $first = $this->makeProvider(['app_dishes_dishlist' => 'https://cinema.meetagain.org']);
        $second = $this->makeProvider(['app_dishes_dishlist' => 'https://dragon.meetagain.org']);
        $service = new UrlOwnerService($this->makeConfig('https://meetagain.org'), [$first, $second]);

        // Act
        $ownerHost = $service->getOwnerHost('app_dishes_dishlist');

        // Assert
        self::assertSame('https://cinema.meetagain.org', $ownerHost);
    }

    public function testProviderDeferralFallsThroughToTheConfiguredHost(): void
    {
        // Arrange
        $service = new UrlOwnerService(
            $this->makeConfig('https://meetagain.org'),
            [$this->makeProvider(['app_dishes_dishlist' => 'https://cinema.meetagain.org'])],
        );

        // Act
        $ownerHost = $service->getOwnerHost('app_login');

        // Assert
        self::assertSame('https://meetagain.org', $ownerHost);
    }

    public function testOwnsUrlComparesHostsOnly(): void
    {
        // Arrange
        $service = new UrlOwnerService(
            $this->makeConfig('https://meetagain.org'),
            [$this->makeProvider(['app_dishes_dishlist' => 'https://cinema.meetagain.org'])],
        );

        // Act + Assert
        self::assertTrue($service->ownsUrl('app_dishes_dishlist', [], 'https://cinema.meetagain.org/en/dishes'));
        self::assertFalse($service->ownsUrl('app_dishes_dishlist', [], 'https://meetagain.org/en/dishes'));
    }

    public function testAnUnclaimedRouteBelongsToWhicheverHostServesIt(): void
    {
        // Arrange
        $service = new UrlOwnerService(
            $this->makeConfig('https://meetagain.org'),
            [$this->makeProvider(['app_dishes_dishlist' => 'https://cinema.meetagain.org'])],
        );

        // Act + Assert
        self::assertTrue($service->ownsUrl('app_login', [], 'https://meetagain.org/en/login'));
        self::assertTrue($service->ownsUrl('app_login', [], 'https://some-other-host.test/en/login'));
    }

    public function testASingleHostInstallKeepsItsWholeFeed(): void
    {
        // Arrange
        $service = new UrlOwnerService($this->makeConfig('https://meetagain.org'), []);

        // Act + Assert
        self::assertTrue($service->ownsUrl('app_login', [], 'https://localhost/en/login'));
    }

    public function testRouteParametersReachTheProvider(): void
    {
        // Arrange
        $provider = $this->createStub(UrlOwnerProviderInterface::class);
        $provider
            ->method('getOwnerHost')
            ->willReturnCallback(
                static fn(string $route, array $parameters) => ($parameters['id'] ?? null) === 17 ? 'https://dragon.meetagain.org' : null,
            );
        $service = new UrlOwnerService($this->makeConfig('https://meetagain.org'), [$provider]);

        // Act + Assert
        self::assertSame('https://dragon.meetagain.org', $service->getOwnerHost('app_event_details', ['id' => 17]));
        self::assertSame('https://meetagain.org', $service->getOwnerHost('app_event_details', ['id' => 18]));
    }

    private function makeConfig(string $host): ConfigService
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn($host);

        return $config;
    }

    /**
     * @param array<string, string> $ownerByRoute
     */
    private function makeProvider(array $ownerByRoute): UrlOwnerProviderInterface
    {
        $provider = $this->createStub(UrlOwnerProviderInterface::class);
        $provider->method('getOwnerHost')->willReturnCallback(static fn(string $route) => $ownerByRoute[$route] ?? null);

        return $provider;
    }
}
