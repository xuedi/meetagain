<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\Publisher\PluginSettings\PluginSettingsResolver;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Config\GlossaryConfig;
use Plugin\Glossary\Service\GlossaryConfigService;

class GlossaryConfigServiceTest extends TestCase
{
    public function testReturnsResolvedConfig(): void
    {
        // Arrange
        $config = (new GlossaryConfig())->setSecondaryEnabled(true);
        $resolver = $this->createStub(PluginSettingsResolver::class);
        $resolver->method('resolve')->willReturn($config);
        $service = new GlossaryConfigService($resolver);

        // Act + Assert
        static::assertSame($config, $service->getConfig());
    }

    public function testMemoizesResolvedConfig(): void
    {
        // Arrange
        $resolver = $this->createMock(PluginSettingsResolver::class);
        $resolver->expects(static::once())->method('resolve')->willReturn(new GlossaryConfig());
        $service = new GlossaryConfigService($resolver);

        // Act
        $first = $service->getConfig();
        $second = $service->getConfig();

        // Assert
        static::assertSame($first, $second);
    }
}
