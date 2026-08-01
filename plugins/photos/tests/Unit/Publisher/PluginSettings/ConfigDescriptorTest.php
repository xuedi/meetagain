<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Publisher\PluginSettings;

use PHPUnit\Framework\TestCase;
use Plugin\Photos\Form\ConfigType;
use Plugin\Photos\Publisher\PluginSettings\ConfigDescriptor;
use Plugin\Photos\ValueObject\Config;

class ConfigDescriptorTest extends TestCase
{
    public function testDescriptorContract(): void
    {
        // Arrange
        $descriptor = new ConfigDescriptor();

        // Act + Assert
        static::assertSame('photos', $descriptor->getKey());
        static::assertSame('photos', $descriptor->getPluginKey());
        static::assertTrue($descriptor->isScopable());
        static::assertSame('photos_config.page_title', $descriptor->getTitleKey());
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
        static::assertTrue($default->isMemberUploads());
        static::assertTrue($default->isShowCameraMeta());
    }
}
