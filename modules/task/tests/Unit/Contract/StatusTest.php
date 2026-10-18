<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Contract;

use Module\Task\Contract\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    #[DataProvider('providePairs')]
    public function testWorstReturnsTheMoreSevereOfTwo(Status $left, Status $right, Status $expected): void
    {
        // Arrange

        // Act
        $worst = $left->worst($right);

        // Assert
        self::assertSame($expected, $worst);
    }

    /**
     * @return iterable<string, array{Status, Status, Status}>
     */
    public static function providePairs(): iterable
    {
        yield 'ok and ok' => [Status::ok, Status::ok, Status::ok];
        yield 'ok and warning' => [Status::ok, Status::warning, Status::warning];
        yield 'error and warning' => [Status::error, Status::warning, Status::error];
        yield 'warning and exception' => [Status::warning, Status::exception, Status::exception];
    }

    public function testOnlyAnOkRunIsTaggedGreen(): void
    {
        // Arrange
        $statuses = Status::cases();

        // Act
        $green = array_filter($statuses, static fn(Status $status): bool => $status->tagVariant() === 'is-success');

        // Assert
        self::assertSame([Status::ok], array_values($green));
    }

    public function testEveryStatusHasItsOwnLabel(): void
    {
        // Arrange
        $statuses = Status::cases();

        // Act
        $labels = array_map(static fn(Status $status): string => $status->label(), $statuses);

        // Assert
        self::assertSame(['admin_task.status_ok', 'admin_task.status_warning', 'admin_task.status_error', 'admin_task.status_exception'], $labels);
    }
}
