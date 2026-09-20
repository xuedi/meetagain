<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Service\Notification\User\NotificationItem;
use PHPUnit\Framework\TestCase;

class NotificationItemKeyTest extends TestCase
{
    public function testAnItemWithoutAKeyFallsBackToItsRouteName(): void
    {
        // Arrange
        $item = new NotificationItem(label: '3 unread messages', icon: 'fa-envelope', route: 'app_profile_messages');

        // Act
        $key = $item->key();

        // Assert
        static::assertSame('app_profile_messages', $key);
    }

    public function testAnExplicitKeyWins(): void
    {
        // Arrange
        $item = new NotificationItem(label: 'waiting for review', route: 'app_profile_review', key: 'review_pending');

        // Act
        $key = $item->key();

        // Assert
        static::assertSame('review_pending', $key);
    }

    public function testAnItemWithNeitherKeyNorRouteHasNoKey(): void
    {
        // Arrange
        $item = new NotificationItem(label: 'nothing to click');

        // Act
        $key = $item->key();

        // Assert
        static::assertNull($key);
    }
}
