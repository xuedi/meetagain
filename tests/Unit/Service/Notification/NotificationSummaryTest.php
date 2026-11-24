<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Service\Notification\User\Level;
use App\Service\Notification\User\NotificationItem;
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

    /** @param list<Level> $levels */
    #[DataProvider('provideMostUrgentLevelCases')]
    public function testTheBellTakesTheMostUrgentLevel(array $levels, ?Level $expected): void
    {
        // Arrange
        $items = array_map(static fn(Level $level) => new NotificationItem(label: 'x', level: $level), $levels);
        $summary = new NotificationSummary(items: $items, totalCount: count($items));

        // Act
        $result = $summary->mostUrgentLevel();

        // Assert
        static::assertSame($expected, $result);
    }

    public static function provideMostUrgentLevelCases(): iterable
    {
        yield 'empty gives none' => [[], null];
        yield 'info alone gives info' => [[Level::Info], Level::Info];
        yield 'info and warning give warning' => [[Level::Info, Level::Warning], Level::Warning];
        yield 'any action gives action' => [[Level::Info, Level::Action, Level::Warning], Level::Action];
    }

    public function testAnItemIsActionByDefault(): void
    {
        // Act
        $item = new NotificationItem(label: 'x');

        // Assert
        static::assertSame(Level::Action, $item->level);
    }
}
