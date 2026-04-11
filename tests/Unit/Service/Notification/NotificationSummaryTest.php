<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Service\Notification\User\NotificationSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationSummaryTest extends TestCase
{
    #[DataProvider('provideHasNotificationsCases')]
    public function testHasNotifications(int $totalCount, bool $expected): void
    {
        // Arrange
        $summary = new NotificationSummary(items: [], totalCount: $totalCount);

        // Act
        $result = $summary->hasNotifications();

        // Assert
        static::assertSame($expected, $result);
    }

    public static function provideHasNotificationsCases(): iterable
    {
        yield 'zero count returns false' => [0, false];
        yield 'positive count returns true' => [3, true];
        yield 'one notification returns true' => [1, true];
    }
}
