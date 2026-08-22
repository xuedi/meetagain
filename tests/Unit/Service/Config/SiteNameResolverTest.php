<?php declare(strict_types=1);

namespace Tests\Unit\Service\Config;

use App\Publisher\SiteName\SiteNameProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\SiteNameResolver;
use PHPUnit\Framework\TestCase;

final class SiteNameResolverTest extends TestCase
{
    public function testTheFirstProviderThatClaimsTheSlotWins(): void
    {
        // Arrange
        $resolver = new SiteNameResolver($this->config('Configured'), [
            $this->provider(null),
            $this->provider('Weiqi Club'),
            $this->provider('Never reached'),
        ]);

        // Act
        $name = $resolver->resolve();

        // Assert
        static::assertSame('Weiqi Club', $name);
    }

    public function testAnEmptyProviderValueDefersLikeNull(): void
    {
        // Arrange
        $resolver = new SiteNameResolver($this->config('Configured'), [$this->provider('')]);

        // Act
        $name = $resolver->resolve();

        // Assert
        static::assertSame('Configured', $name);
    }

    public function testWithoutProvidersTheConfiguredNameIsUsed(): void
    {
        // Arrange
        $resolver = new SiteNameResolver($this->config('Configured'));

        // Act
        $name = $resolver->resolve();

        // Assert
        static::assertSame('Configured', $name);
    }

    private function config(string $name): ConfigService
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getSiteName')->willReturn($name);

        return $config;
    }

    private function provider(?string $name): SiteNameProviderInterface
    {
        $provider = $this->createStub(SiteNameProviderInterface::class);
        $provider->method('getSiteName')->willReturn($name);

        return $provider;
    }
}
