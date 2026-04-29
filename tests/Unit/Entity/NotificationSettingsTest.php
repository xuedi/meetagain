<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\NotificationSettings;
use PHPUnit\Framework\TestCase;

class NotificationSettingsTest extends TestCase
{
    public function testFollowingUpdatesDefaultsToFalseForEmptyData(): void
    {
        // Arrange + Act
        $settings = new NotificationSettings([]);

        // Assert
        static::assertFalse($settings->followingUpdates);
    }

    public function testOtherKeysStillDefaultToTrueForEmptyData(): void
    {
        // Arrange + Act
        $settings = new NotificationSettings([]);

        // Assert
        static::assertTrue($settings->announcements);
        static::assertTrue($settings->receivedMessage);
        static::assertTrue($settings->eventReminder);
        static::assertTrue($settings->upcomingEvents);
    }

    public function testFromJsonNullProducesFollowingUpdatesFalse(): void
    {
        // Arrange + Act
        $settings = NotificationSettings::fromJson(null);

        // Assert
        static::assertFalse($settings->followingUpdates);
    }

    public function testExplicitTrueIsRespected(): void
    {
        // Arrange + Act
        $settings = new NotificationSettings(['followingUpdates' => true]);

        // Assert
        static::assertTrue($settings->followingUpdates);
    }

    public function testExplicitFalseIsRespected(): void
    {
        // Arrange + Act
        $settings = new NotificationSettings(['followingUpdates' => false]);

        // Assert
        static::assertFalse($settings->followingUpdates);
    }
}
