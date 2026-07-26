<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Service\Event\OccurrenceCalculator;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The matrix fixture records rlanvin/php-rrule v2.6, including its WKST=MO default - the behaviour
 * production had before the engine was brought in-house. Regenerate it only against a reference
 * RFC-5545 implementation, never from OccurrenceCalculator itself.
 */
class OccurrenceCalculatorTest extends TestCase
{
    private const int TAKE_COUNT = 8;
    private const string FIXTURE = __DIR__ . '/Fixtures/occurrence_matrix.txt';

    #[DataProvider('provideMatrix')]
    public function testMatchesTheReferenceImplementation(string $spec, string $anchor, string $expectedTake, string $expectedWindow): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::fromRfcString($spec);
        $anchorDate = new DateTimeImmutable($anchor);

        // Act
        $taken = $calculator->take($pattern, $anchorDate, self::TAKE_COUNT);
        $windowed = $calculator->until($pattern, $anchorDate, $anchorDate->modify($pattern->period->lookaheadModifier()));

        // Assert
        static::assertSame($expectedTake, self::join($taken), 'take() diverges from the reference');
        static::assertSame($expectedWindow, self::join($windowed), 'until() diverges from the reference');
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideMatrix(): iterable
    {
        foreach (file(self::FIXTURE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            [$spec, $anchor, $take, $window] = explode('|', $line);

            yield $spec . ' @ ' . $anchor => [$spec, $anchor, $take, $window];
        }
    }

    public function testABiweeklyRuleCountsWeeksFromMondayNotFromTheAnchor(): void
    {
        // Arrange: Sunday 4 Jan belongs to the Mon-Sun week that started on 29 Dec, so the next
        // interval week begins 12 Jan - eight days out, not fourteen.
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(RecurrencePeriod::TwoWeeks, Weekday::Wednesday);

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-04'), 3);

        // Assert
        static::assertSame('2026-01-14,2026-01-28,2026-02-11', self::join($occurrences));
    }

    public function testAMonthlyIntervalCountsFromTheAnchorMonthAndDropsPreAnchorCandidates(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::Quarter, 15);

        // Act: the 15th of the anchor's own month is already past, so January yields nothing
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-20'), 3);

        // Assert
        static::assertSame('2026-04-15,2026-07-15,2026-10-15', self::join($occurrences));
    }

    public function testMonthsWithoutTheRequestedDayAreSkippedRatherThanShifted(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::TwoMonths, 31);

        // Act: from February the interval months are Feb, Apr, Jun, Aug - only August has a 31st
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-02-01'), 3);

        // Assert
        static::assertSame('2026-08-31,2026-10-31,2026-12-31', self::join($occurrences));
    }

    public function testTheAnchorsOwnDateIsExcludedWhenItMatchesTheRule(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(RecurrencePeriod::Month, Weekday::Sunday, RecurrenceOrdinal::First);

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-02-01'), 2);

        // Assert
        static::assertSame('2026-03-01,2026-04-05', self::join($occurrences));
    }

    public function testAnOffPatternAnchorDoesNotCostTheFirstOccurrence(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(RecurrencePeriod::Month, Weekday::Sunday, RecurrenceOrdinal::First);

        // Act: 4 Feb is a Wednesday, so February's first Sunday is behind us but March's is not
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-02-04'), 2);

        // Assert
        static::assertSame('2026-03-01,2026-04-05', self::join($occurrences));
    }

    public function testASparseLeapDayRuleStillTerminatesAndSkipsThreeYearsOutOfFour(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::Year, 29, 2);

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2028-02-29'), 3);

        // Assert
        static::assertSame('2032-02-29,2036-02-29,2040-02-29', self::join($occurrences));
    }

    public function testSeveralWeekdaysInOneWeekComeBackInCalendarOrder(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(RecurrencePeriod::Week, [Weekday::Friday, Weekday::Monday, Weekday::Wednesday]);

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-07'), 4);

        // Assert
        static::assertSame('2026-01-09,2026-01-12,2026-01-14,2026-01-16', self::join($occurrences));
    }

    public function testTwoOrdinalsCollapsingOntoTheSameDateYieldItOnce(): void
    {
        // Arrange: February 2026 has exactly four Fridays, so the fourth is also the last
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(
            RecurrencePeriod::Month,
            Weekday::Friday,
            [RecurrenceOrdinal::Fourth, RecurrenceOrdinal::Last],
        );

        // Act
        $occurrences = $calculator->until($pattern, new DateTimeImmutable('2026-02-01'), new DateTimeImmutable('2026-02-28'));

        // Assert
        static::assertSame('2026-02-27', self::join($occurrences));
    }

    public function testSeveralDaysPerMonthComeBackInCalendarOrder(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::Month, [15, 1]);

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-04'), 5);

        // Assert
        static::assertSame('2026-01-15,2026-02-01,2026-02-15,2026-03-01,2026-03-15', self::join($occurrences));
    }

    public function testADayMissingFromAShortMonthDropsOutWithoutLosingTheOthers(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::dayOfMonth(RecurrencePeriod::Month, [28, 31]);

        // Act: February 2026 keeps the 28th and simply has no 31st
        $occurrences = $calculator->until(
            $pattern,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
        );

        // Assert
        static::assertSame(
            '2026-01-28,2026-01-31,2026-02-28,2026-03-28,2026-03-31',
            self::join($occurrences),
        );
    }

    public function testAWindowEndingBeforeTheFirstOccurrenceReturnsNothing(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::weekday(RecurrencePeriod::Month, Weekday::Sunday, RecurrenceOrdinal::First);

        // Act
        $occurrences = $calculator->until($pattern, new DateTimeImmutable('2026-02-04'), new DateTimeImmutable('2026-02-20'));

        // Assert
        static::assertSame([], $occurrences);
    }

    #[DataProvider('provideNonPositiveCounts')]
    public function testANonPositiveCountReturnsNothing(int $count): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::daily();

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-04'), $count);

        // Assert
        static::assertSame([], $occurrences);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositiveCounts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-3];
    }

    public function testTheAnchorsTimeOfDayNeverLeaksIntoTheResult(): void
    {
        // Arrange
        $calculator = new OccurrenceCalculator();
        $pattern = RecurrencePattern::daily();

        // Act
        $occurrences = $calculator->take($pattern, new DateTimeImmutable('2026-01-04 19:45:12'), 2);

        // Assert
        static::assertSame('2026-01-05 00:00:00', $occurrences[0]->format('Y-m-d H:i:s'));
        static::assertSame('2026-01-06 00:00:00', $occurrences[1]->format('Y-m-d H:i:s'));
    }

    /**
     * @param list<DateTimeImmutable> $occurrences
     */
    private static function join(array $occurrences): string
    {
        return implode(',', array_map(static fn(DateTimeImmutable $date): string => $date->format('Y-m-d'), $occurrences));
    }
}
