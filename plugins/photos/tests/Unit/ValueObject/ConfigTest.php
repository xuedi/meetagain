<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Photos\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testEveryToggleDefaultsToOn(): void
    {
        // Act
        $config = new Config();

        // Assert
        static::assertTrue($config->isMemberUploads());
        static::assertTrue($config->isShowCameraMeta());
        static::assertTrue($config->isMemberStreams());
        static::assertTrue($config->isEventBox());
        static::assertFalse($config->isContest());
        static::assertSame(1, $config->getContestSubmissionsPerMember());
    }

    public function testRoundTripsThroughTheStoredArray(): void
    {
        // Arrange
        $config = new Config()->setMemberUploads(false)->setShowCameraMeta(false)->setMemberStreams(false)->setEventBox(false)->setContest(true)->setContestSubmissionsPerMember(3);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertSame(
            [
                'memberUploads' => false,
                'showCameraMeta' => false,
                'memberStreams' => false,
                'eventBox' => false,
                'contest' => true,
                'contestSubmissionsPerMember' => 3,
            ],
            $restored->toArray(),
        );
    }

    public function testAnEmptyStoredArrayKeepsTheNeutralDefaults(): void
    {
        // Act
        $config = Config::fromArray([]);

        // Assert
        static::assertTrue($config->isMemberUploads());
        static::assertTrue($config->isShowCameraMeta());
        static::assertTrue($config->isMemberStreams());
        static::assertTrue($config->isEventBox());
        static::assertFalse($config->isContest());
        static::assertSame(1, $config->getContestSubmissionsPerMember());
    }
}
