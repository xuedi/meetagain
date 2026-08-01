<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Photos\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testBothTogglesDefaultToOn(): void
    {
        // Act
        $config = new Config();

        // Assert
        static::assertTrue($config->isMemberUploads());
        static::assertTrue($config->isShowCameraMeta());
    }

    public function testRoundTripsThroughTheStoredArray(): void
    {
        // Arrange
        $config = new Config()->setMemberUploads(false)->setShowCameraMeta(false);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertSame(['memberUploads' => false, 'showCameraMeta' => false], $restored->toArray());
    }

    public function testAnEmptyStoredArrayKeepsTheNeutralDefaults(): void
    {
        // Act
        $config = Config::fromArray([]);

        // Assert
        static::assertTrue($config->isMemberUploads());
        static::assertTrue($config->isShowCameraMeta());
    }
}
