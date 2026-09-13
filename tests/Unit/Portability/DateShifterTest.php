<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Portability\DateShifter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateShifterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function weeksProvider(): iterable
    {
        yield 'two days of the same week' => ['2026-09-14', '2026-09-20', 0];
        yield 'a sunday and the next monday' => ['2026-09-20', '2026-09-21', 1];
        yield 'backwards in time' => ['2026-09-16', '2026-01-07', -36];
        yield 'across the spring clock change' => ['2026-03-23', '2026-03-30', 1];
    }

    #[DataProvider('weeksProvider')]
    public function testWeeksAreCountedBetweenTheMondaysOfBothDates(string $from, string $to, int $expected): void
    {
        // Act
        $weeks = new DateShifter()->weeksBetween(new DateTimeImmutable($from), new DateTimeImmutable($to));

        // Assert
        static::assertSame($expected, $weeks);
    }

    public function testShiftingKeepsTheWeekdayAndTheTimeOfDay(): void
    {
        // Arrange
        $data = ['events' => [['start' => '2026-01-07T19:00:00+01:00', 'stop' => '2026-01-07T22:30:00+01:00']]];

        // Act
        $shifted = new DateShifter()->shift($data, 36);

        // Assert
        $start = (string) $shifted['events'][0]['start'];
        static::assertSame('2026-09-16T19:00:00', substr($start, 0, 19));
        static::assertSame('2026-09-16T22:30:00', substr((string) $shifted['events'][0]['stop'], 0, 19));
        static::assertSame('Wednesday', new DateTimeImmutable($start)->format('l'));
    }

    public function testValuesThatAreNotTimestampsStayUntouched(): void
    {
        // Arrange
        $data = [
            'taken_at' => '2026-01-07 19:00:00',
            'day' => '2026-01-07',
            'title' => 'Go night',
            'year' => 1995,
            'nested' => ['note' => '2026-01-07T19:00'],
        ];

        // Act
        $shifted = new DateShifter()->shift($data, 36);

        // Assert
        static::assertSame($data, $shifted);
    }

    public function testZeroWeeksLeavesTheDataAsItIs(): void
    {
        // Arrange
        $data = ['start' => '2026-01-07T19:00:00+05:00'];

        // Act
        $shifted = new DateShifter()->shift($data, 0);

        // Assert
        static::assertSame($data, $shifted);
    }

    public function testTheMondayOfADateIsMidnightAtTheStartOfItsWeek(): void
    {
        // Act
        $monday = new DateShifter()->mondayOf(new DateTimeImmutable('2026-09-20 15:00'));

        // Assert
        static::assertSame('2026-09-14 00:00', $monday->format('Y-m-d H:i'));
    }
}
