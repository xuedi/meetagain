<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Publisher\PluginSettings;

use PHPUnit\Framework\TestCase;
use Plugin\Dishes\Config;
use Plugin\Dishes\Form\ConfigType;
use Plugin\Dishes\Publisher\PluginSettings\ConfigDescriptor;

class ConfigDescriptorTest extends TestCase
{
    public function testDescriptorContract(): void
    {
        // Arrange
        $descriptor = new ConfigDescriptor();

        // Act + Assert
        static::assertSame('dishes', $descriptor->getKey());
        static::assertSame('dishes_config.page_title', $descriptor->getTitleKey());
        static::assertSame(ConfigType::class, $descriptor->getFormType());
        static::assertSame([], $descriptor->getFormOptions(new Config()));
        static::assertSame(0, $descriptor->getPriority());
    }

    public function testCreateDefaultIsNeutralConfig(): void
    {
        // Arrange
        $descriptor = new ConfigDescriptor();

        // Act
        $default = $descriptor->createDefault();

        // Assert
        static::assertInstanceOf(Config::class, $default);
        static::assertSame([], $default->getFooterText());
    }
}
