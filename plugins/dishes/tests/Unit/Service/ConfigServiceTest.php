<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use PHPUnit\Framework\TestCase;
use Plugin\Dishes\ValueObject\Config;
use Plugin\Dishes\Service\ConfigService;

class ConfigServiceTest extends TestCase
{
    public function testReturnsResolvedConfig(): void
    {
        // Arrange
        $config = new Config()->setFooterText(['en' => 'Footer']);
        $resolver = $this->createStub(PluginSettingsResolver::class);
        $resolver->method('resolve')->willReturn($config);
        $service = new ConfigService($resolver);

        // Act + Assert
        static::assertSame($config, $service->getConfig());
    }

    public function testMemoizesResolvedConfig(): void
    {
        // Arrange
        $resolver = $this->createMock(PluginSettingsResolver::class);
        $resolver->expects(static::once())->method('resolve')->willReturn(new Config());
        $service = new ConfigService($resolver);

        // Act
        $first = $service->getConfig();
        $second = $service->getConfig();

        // Assert
        static::assertSame($first, $second);
    }
}
