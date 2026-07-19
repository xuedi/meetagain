<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\ValueObject\Config;

class ConfigServiceTest extends TestCase
{
    public function testReturnsResolvedConfig(): void
    {
        // Arrange
        $config = (new Config())->setSecondaryEnabled(true);
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
