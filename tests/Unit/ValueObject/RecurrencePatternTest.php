<?php declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use App\Enum\EventInterval;
use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecurrencePatternTest extends TestCase
{
    #[DataProvider('provideWeekdayPatterns')]
    public function testWeekdayPatternSerialisesToRfcString(
        RecurrencePeriod $period,
        Weekday $weekday,
        ?RecurrenceOrdinal $ordinal,
        ?int $anchorMonth,
        string $expected,
    ): void {
        // Act
        $pattern = RecurrencePattern::weekday($period, $weekday, $ordinal, $anchorMonth);

        // Assert
        static::assertSame($expected, $pattern->toRfcString());
    }

    public static function provideWeekdayPatterns(): iterable
    {
        yield 'weekly ignores the ordinal' => [
            RecurrencePeriod::Week, Weekday::Sunday, null, null, 'FREQ=WEEKLY;BYDAY=SU',
        ];
        yield 'every two weeks' => [
            RecurrencePeriod::TwoWeeks, Weekday::Friday, null, null, 'FREQ=WEEKLY;INTERVAL=2;BYDAY=FR',
        ];
        yield 'first Sunday of the month' => [
            RecurrencePeriod::Month, Weekday::Sunday, RecurrenceOrdinal::First, null, 'FREQ=MONTHLY;BYDAY=1SU',
        ];
        yield 'last Friday of the month' => [
            RecurrencePeriod::Month, Weekday::Friday, RecurrenceOrdinal::Last, null, 'FREQ=MONTHLY;BYDAY=-1FR',
        ];
        yield 'second Saturday every two months' => [
            RecurrencePeriod::TwoMonths, Weekday::Saturday, RecurrenceOrdinal::Second, null,
            'FREQ=MONTHLY;INTERVAL=2;BYDAY=2SA',
        ];
        yield 'third Monday every quarter' => [
            RecurrencePeriod::Quarter, Weekday::Monday, RecurrenceOrdinal::Third, null,
            'FREQ=MONTHLY;INTERVAL=3;BYDAY=3MO',
        ];
        yield 'fourth Thursday of August each year' => [
            RecurrencePeriod::Year, Weekday::Thursday, RecurrenceOrdinal::Fourth, 8,
            'FREQ=YEARLY;BYMONTH=8;BYDAY=4TH',
        ];
    }

    #[DataProvider('provideDayOfMonthPatterns')]
    public function testDayOfMonthPatternSerialisesToRfcString(
        RecurrencePeriod $period,
        int $dayOfMonth,
        ?int $anchorMonth,
        string $expected,
    ): void {
        // Act
        $pattern = RecurrencePattern::dayOfMonth($period, $dayOfMonth, $anchorMonth);

        // Assert
        static::assertSame($expected, $pattern->toRfcString());
    }

    public static function provideDayOfMonthPatterns(): iterable
    {
        yield 'fifteenth of the month' => [
            RecurrencePeriod::Month, 15, null, 'FREQ=MONTHLY;BYMONTHDAY=15',
        ];
        yield 'last day of the month' => [
            RecurrencePeriod::Month, RecurrencePattern::LAST_DAY_OF_MONTH, null, 'FREQ=MONTHLY;BYMONTHDAY=-1',
        ];
        yield 'first every two months' => [
            RecurrencePeriod::TwoMonths, 1, null, 'FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=1',
        ];
        yield 'thirty-first every quarter' => [
            RecurrencePeriod::Quarter, 31, null, 'FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=31',
        ];
        yield 'fifteenth of August each year' => [
            RecurrencePeriod::Year, 15, 8, 'FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=15',
        ];
    }

    #[DataProvider('provideRoundTripSpecs')]
    public function testFromRfcStringRoundTrips(string $spec): void
    {
        // Act
        $pattern = RecurrencePattern::fromRfcString($spec);

        // Assert
        static::assertSame($spec, $pattern->toRfcString());
    }

    public static function provideRoundTripSpecs(): iterable
    {
        yield 'weekly' => ['FREQ=WEEKLY;BYDAY=SU'];
        yield 'two weeks' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=FR'];
        yield 'first Sunday monthly' => ['FREQ=MONTHLY;BYDAY=1SU'];
        yield 'last Friday monthly' => ['FREQ=MONTHLY;BYDAY=-1FR'];
        yield 'second Saturday two-monthly' => ['FREQ=MONTHLY;INTERVAL=2;BYDAY=2SA'];
        yield 'third Monday quarterly' => ['FREQ=MONTHLY;INTERVAL=3;BYDAY=3MO'];
        yield 'fourth Thursday yearly' => ['FREQ=YEARLY;BYMONTH=8;BYDAY=4TH'];
        yield 'day of month' => ['FREQ=MONTHLY;BYMONTHDAY=15'];
        yield 'last day of month' => ['FREQ=MONTHLY;BYMONTHDAY=-1'];
        yield 'day of month quarterly' => ['FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=31'];
        yield 'day of month yearly' => ['FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=15'];
    }

    public function testFromRfcStringResolvesTheParsedParts(): void
    {
        // Act
        $pattern = RecurrencePattern::fromRfcString('FREQ=MONTHLY;INTERVAL=3;BYDAY=-1FR');

        // Assert
        static::assertSame(RecurrenceMode::Weekday, $pattern->mode);
        static::assertSame(RecurrencePeriod::Quarter, $pattern->period);
        static::assertSame([RecurrenceOrdinal::Last], $pattern->ordinals);
        static::assertSame([Weekday::Friday], $pattern->weekdays);
        static::assertSame([], $pattern->daysOfMonth);
    }

    #[DataProvider('provideMultiEntryPatterns')]
    public function testSeveralByDayEntriesRoundTripInCalendarOrder(RecurrencePeriod $period, array $weekdays, array $ordinals, string $expected): void
    {
        // Act
        $pattern = RecurrencePattern::weekday($period, $weekdays, $ordinals);

        // Assert
        static::assertSame($expected, $pattern->toRfcString());
        static::assertSame($expected, RecurrencePattern::fromRfcString($expected)->toRfcString());
    }

    public static function provideMultiEntryPatterns(): iterable
    {
        yield 'several weekdays in a week' => [
            RecurrencePeriod::Week,
            [Weekday::Friday, Weekday::Monday, Weekday::Wednesday],
            [],
            'FREQ=WEEKLY;BYDAY=MO,WE,FR',
        ];
        yield 'weekend every two weeks' => [
            RecurrencePeriod::TwoWeeks,
            [Weekday::Sunday, Weekday::Saturday],
            [],
            'FREQ=WEEKLY;INTERVAL=2;BYDAY=SA,SU',
        ];
        yield 'first and third Friday' => [
            RecurrencePeriod::Month,
            [Weekday::Friday],
            [RecurrenceOrdinal::Third, RecurrenceOrdinal::First],
            'FREQ=MONTHLY;BYDAY=1FR,3FR',
        ];
        yield 'last sorts after the numbered ordinals' => [
            RecurrencePeriod::Quarter,
            [Weekday::Monday],
            [RecurrenceOrdinal::Last, RecurrenceOrdinal::Second],
            'FREQ=MONTHLY;INTERVAL=3;BYDAY=2MO,-1MO',
        ];
        yield 'duplicates collapse' => [
            RecurrencePeriod::Week,
            [Weekday::Monday, Weekday::Monday],
            [],
            'FREQ=WEEKLY;BYDAY=MO',
        ];
    }

    #[DataProvider('provideRejectedMultiEntryPatterns')]
    public function testSeveralByDayEntriesRejectMixedShapes(RecurrencePeriod $period, array $weekdays, array $ordinals): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::weekday($period, $weekdays, $ordinals);
    }

    public static function provideRejectedMultiEntryPatterns(): iterable
    {
        yield 'monthly needs exactly one weekday' => [
            RecurrencePeriod::Month,
            [Weekday::Friday, Weekday::Monday],
            [RecurrenceOrdinal::First],
        ];
        yield 'no weekday at all' => [RecurrencePeriod::Week, [], []];
    }

    #[DataProvider('provideImpossibleYearlyDays')]
    public function testAYearlyDayThatTheMonthNeverHasIsRejected(int $dayOfMonth, int $anchorMonth): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::dayOfMonth(RecurrencePeriod::Year, $dayOfMonth, $anchorMonth);
    }

    public static function provideImpossibleYearlyDays(): iterable
    {
        yield '30 February' => [30, 2];
        yield '31 February' => [31, 2];
        yield '31 April' => [31, 4];
        yield '31 November' => [31, 11];
    }

    #[DataProvider('provideMultiDayPatterns')]
    public function testSeveralDaysOfMonthRoundTripInAscendingOrder(RecurrencePeriod $period, array $days, string $expected): void
    {
        // Act
        $pattern = RecurrencePattern::dayOfMonth($period, $days);

        // Assert
        static::assertSame($expected, $pattern->toRfcString());
        static::assertSame($expected, RecurrencePattern::fromRfcString($expected)->toRfcString());
        static::assertFalse($pattern->isLastDayOfMonth());
    }

    public static function provideMultiDayPatterns(): iterable
    {
        yield 'first and fifteenth' => [RecurrencePeriod::Month, [15, 1], 'FREQ=MONTHLY;BYMONTHDAY=1,15'];
        yield 'duplicates collapse' => [RecurrencePeriod::Month, [10, 10], 'FREQ=MONTHLY;BYMONTHDAY=10'];
        yield 'three days quarterly' => [
            RecurrencePeriod::Quarter, [28, 5, 12], 'FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=5,12,28',
        ];
    }

    public function testTheLastDayCannotJoinNumberedDays(): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::dayOfMonth(RecurrencePeriod::Month, [1, RecurrencePattern::LAST_DAY_OF_MONTH]);
    }

    public function testAYearlyLeapDayIsAllowed(): void
    {
        // Act
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::Year, 29, 2);

        // Assert
        static::assertSame('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', $pattern->toRfcString());
    }

    public function testADailyPatternRoundTrips(): void
    {
        // Act
        $pattern = RecurrencePattern::fromRfcString('FREQ=DAILY');

        // Assert
        static::assertSame(RecurrenceMode::EveryDay, $pattern->mode);
        static::assertSame(RecurrencePeriod::Day, $pattern->period);
        static::assertSame('FREQ=DAILY', $pattern->toRfcString());
    }

    #[DataProvider('provideRejectedSpecs')]
    public function testFromRfcStringRejectsUnsupportedShapes(string $spec): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::fromRfcString($spec);
    }

    public static function provideRejectedSpecs(): iterable
    {
        yield 'empty string' => [''];
        yield 'malformed segment' => ['FREQ=MONTHLY;BYDAY'];
        yield 'unsupported frequency' => ['FREQ=HOURLY;BYDAY=1SU'];
        yield 'unsupported interval' => ['FREQ=MONTHLY;INTERVAL=7;BYDAY=1SU'];
        yield 'no BYDAY or BYMONTHDAY' => ['FREQ=MONTHLY'];
        yield 'mixed ordinal and bare weekday' => ['FREQ=MONTHLY;BYDAY=1FR,MO'];
        yield 'several weekdays under one ordinal' => ['FREQ=MONTHLY;BYDAY=1FR,3MO'];
        yield 'unsupported ordinal' => ['FREQ=MONTHLY;BYDAY=5SU'];
        yield 'last day mixed with a numbered day' => ['FREQ=MONTHLY;BYMONTHDAY=1,-1'];
        yield 'daily carrying a BYDAY' => ['FREQ=DAILY;BYDAY=MO'];
        yield 'monthly weekday without ordinal' => ['FREQ=MONTHLY;BYDAY=SU'];
        yield 'day of month out of range' => ['FREQ=MONTHLY;BYMONTHDAY=32'];
        yield 'yearly without anchor month' => ['FREQ=YEARLY;BYDAY=1SU'];
    }

    public function testDayOfMonthIsRejectedForWeeklyPeriods(): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::dayOfMonth(RecurrencePeriod::Week, 15);
    }

    public function testMonthlyWeekdayPatternRequiresAnOrdinal(): void
    {
        // Assert
        $this->expectException(InvalidRecurrencePatternException::class);

        // Act
        RecurrencePattern::weekday(RecurrencePeriod::Month, Weekday::Sunday);
    }

    #[DataProvider('providePresetBridgeCases')]
    public function testFromIntervalBridgesThePresets(EventInterval $interval, ?string $expected): void
    {
        // Arrange
        $anchor = new DateTimeImmutable('2026-08-12 19:00:00');

        // Act
        $pattern = RecurrencePattern::fromInterval($interval, $anchor);

        // Assert
        static::assertSame($expected, $pattern?->toRfcString());
    }

    public static function providePresetBridgeCases(): iterable
    {
        yield 'weekly takes the anchor weekday' => [EventInterval::Weekly, 'FREQ=WEEKLY;BYDAY=WE'];
        yield 'bimonthly becomes two weeks' => [EventInterval::BiMonthly, 'FREQ=WEEKLY;INTERVAL=2;BYDAY=WE'];
        yield 'monthly takes the anchor day' => [EventInterval::Monthly, 'FREQ=MONTHLY;BYMONTHDAY=12'];
        yield 'yearly takes anchor day and month' => [EventInterval::Yearly, 'FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=12'];
        yield 'daily needs nothing from the anchor' => [EventInterval::Daily, 'FREQ=DAILY'];
        yield 'custom resolves from its own spec' => [EventInterval::Custom, null];
    }
}
