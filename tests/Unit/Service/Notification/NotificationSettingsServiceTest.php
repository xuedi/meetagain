<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Service\Notification\NotificationSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NotificationSettingsServiceTest extends TestCase
{
    public function testTogglingEachKnownKeyFlipsOnlyThatKey(): void
    {
        // Arrange
        $user = new User();
        $service = new NotificationSettingsService($this->entityManager());
        $before = $user->getNotificationSettings()->jsonSerialize();

        // Act
        $service->toggle($user, 'eventReminder');

        // Assert
        $after = $user->getNotificationSettings()->jsonSerialize();
        static::assertNotSame($before['eventReminder'], $after['eventReminder']);
        foreach (['announcements', 'followingUpdates', 'receivedMessage', 'upcomingEvents', 'attendedEventUpdate'] as $untouched) {
            static::assertSame($before[$untouched], $after[$untouched]);
        }
    }

    public function testToggleReturnsTheNewState(): void
    {
        // Arrange
        $user = new User();
        $service = new NotificationSettingsService($this->entityManager());

        // Act
        $first = $service->toggle($user, 'announcements');
        $second = $service->toggle($user, 'announcements');

        // Assert
        static::assertFalse($first);
        static::assertTrue($second);
    }

    public function testTogglingAnUnknownKeyIsRejected(): void
    {
        // Arrange
        $user = new User();
        $service = new NotificationSettingsService($this->entityManager());

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->toggle($user, 'notAKey');
    }

    public function testApplyChangesOnlyTheKeysItIsGiven(): void
    {
        // Arrange
        $user = new User();
        $service = new NotificationSettingsService($this->entityManager());

        // Act
        $service->apply($user, ['announcements' => false, 'followingUpdates' => true]);

        // Assert
        $settings = $user->getNotificationSettings();
        static::assertFalse($settings->announcements);
        static::assertTrue($settings->followingUpdates);
        static::assertTrue($settings->receivedMessage);
        static::assertTrue($settings->eventReminder);
    }

    public function testApplyRejectsAnUnknownKeyAndChangesNothing(): void
    {
        // Arrange
        $user = new User();
        $service = new NotificationSettingsService($this->entityManager());

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->apply($user, ['announcements' => false, 'notAKey' => true]);
    }

    public function testTogglingDoesNotTouchTheMasterSwitch(): void
    {
        // Arrange
        $user = new User();
        $user->setNotification(true);
        $service = new NotificationSettingsService($this->entityManager());

        // Act
        $service->toggle($user, 'receivedMessage');
        $service->apply($user, ['upcomingEvents' => false]);

        // Assert
        static::assertTrue($user->isNotification());
    }

    public function testTheMasterSwitchHasItsOwnWriter(): void
    {
        // Arrange
        $user = new User();
        $user->setNotification(true);
        $service = new NotificationSettingsService($this->entityManager());

        // Act
        $service->setMasterSwitch($user, false);

        // Assert
        static::assertFalse($user->isNotification());
        static::assertTrue($user->getNotificationSettings()->announcements);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }
}
