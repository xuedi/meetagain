<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Service\Notification\User\ReviewNotificationItem;
use PHPUnit\Framework\TestCase;

class ReviewNotificationItemTest extends TestCase
{
    public function testDefaultCanDenyIsTrue(): void
    {
        // Arrange & Act
        $item = new ReviewNotificationItem(id: '1', description: 'Test item');

        // Assert
        static::assertTrue($item->canDeny);
    }

    public function testDefaultIconIsNull(): void
    {
        // Arrange & Act
        $item = new ReviewNotificationItem(id: '1', description: 'Test item');

        // Assert
        static::assertNull($item->icon);
    }

    public function testExplicitValuesAreStored(): void
    {
        // Arrange & Act
        $item = new ReviewNotificationItem(
            id: '42',
            description: 'User wants to join',
            canDeny: false,
            icon: 'user-check',
        );

        // Assert
        static::assertSame('42', $item->id);
        static::assertSame('User wants to join', $item->description);
        static::assertFalse($item->canDeny);
        static::assertSame('user-check', $item->icon);
    }
}
