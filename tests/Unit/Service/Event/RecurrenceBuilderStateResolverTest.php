<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Service\Event\RecurrenceBuilderStateResolver;
use App\ValueObject\RecurrencePattern;
use PHPUnit\Framework\TestCase;

class RecurrenceBuilderStateResolverTest extends TestCase
{
    private function resolve(
        RecurrenceMode $mode,
        RecurrencePeriod $period,
        array $ordinals = [],
        array $weekdays = [],
        array $daysOfMonth = [],
    ) {
        return new RecurrenceBuilderStateResolver()
            ->resolve($mode, $period, $ordinals, $weekdays, $daysOfMonth, Weekday::Thursday);
    }

    public function testAWeeklyPeriodDropsOrdinalsAndKeepsEveryWeekday(): void
    {
        // Act
        $state = $this->resolve(
            RecurrenceMode::Weekday,
            RecurrencePeriod::TwoWeeks,
            [RecurrenceOrdinal::First],
            [Weekday::Friday, Weekday::Monday],
        );

        // Assert
        static::assertSame([], $state->ordinals);
        static::assertSame([Weekday::Monday, Weekday::Friday], $state->weekdays);
        static::assertFalse($state->showsOrdinal());
        static::assertTrue($state->allowsSeveralWeekdays());
        static::assertSame('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR', $state->pattern()->toRfcString());
    }

    public function testAMonthlyPeriodTrimsToOneWeekdayAndDefaultsTheOrdinal(): void
    {
        // Act
        $state = $this->resolve(
            RecurrenceMode::Weekday,
            RecurrencePeriod::Month,
            [],
            [Weekday::Friday, Weekday::Monday],
        );

        // Assert
        static::assertSame([RecurrenceOrdinal::First], $state->ordinals);
        static::assertSame([Weekday::Monday], $state->weekdays);
        static::assertTrue($state->showsOrdinal());
        static::assertFalse($state->allowsSeveralWeekdays());
        static::assertSame('FREQ=MONTHLY;BYDAY=1MO', $state->pattern()->toRfcString());
    }

    public function testADayOfMonthSelectionMovesOffAWeeklyPeriod(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::DayOfMonth, RecurrencePeriod::Week, [], [], [15]);

        // Assert
        static::assertSame(RecurrencePeriod::Month, $state->period);
        static::assertSame('FREQ=MONTHLY;BYMONTHDAY=15', $state->pattern()->toRfcString());
    }

    public function testADayOfMonthSelectionOffersNoWeeklyPeriods(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::DayOfMonth, RecurrencePeriod::Quarter, [], [], [15]);

        // Assert
        static::assertSame(
            [RecurrencePeriod::Month, RecurrencePeriod::TwoMonths, RecurrencePeriod::Quarter, RecurrencePeriod::Year],
            $state->periods,
        );
    }

    public function testAWeekdaySelectionOffersEveryPeriodExceptDay(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::Weekday, RecurrencePeriod::Month);

        // Assert
        static::assertNotContains(RecurrencePeriod::Day, $state->periods);
        static::assertContains(RecurrencePeriod::Week, $state->periods);
        static::assertCount(6, $state->periods);
    }

    public function testTheBuilderNeverAuthorsADailyRule(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::EveryDay, RecurrencePeriod::Day);

        // Assert
        static::assertSame(RecurrenceMode::Weekday, $state->mode);
        static::assertSame(RecurrencePeriod::Month, $state->period);
    }

    public function testAnEmptyWeekdaySelectionFallsBackToTheAnchorWeekday(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::Weekday, RecurrencePeriod::Week);

        // Assert
        static::assertSame([Weekday::Thursday], $state->weekdays);
    }

    public function testAnOutOfRangeDayFallsBackToTheFirst(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::DayOfMonth, RecurrencePeriod::Month, [], [], [99]);

        // Assert
        static::assertSame([1], $state->daysOfMonth);
    }

    public function testTheLastDayOfMonthSurvivesNormalisation(): void
    {
        // Act
        $state = $this->resolve(
            RecurrenceMode::DayOfMonth,
            RecurrencePeriod::Month,
            [],
            [],
            [RecurrencePattern::LAST_DAY_OF_MONTH],
        );

        // Assert
        static::assertSame([RecurrencePattern::LAST_DAY_OF_MONTH], $state->daysOfMonth);
        static::assertFalse($state->warnsAboutShortMonths());
        static::assertSame('FREQ=MONTHLY;BYMONTHDAY=-1', $state->pattern()->toRfcString());
    }

    public function testDaysThatShortMonthsLackRaiseTheHint(): void
    {
        // Act
        $state = $this->resolve(RecurrenceMode::DayOfMonth, RecurrencePeriod::Month, [], [], [31]);

        // Assert
        static::assertTrue($state->warnsAboutShortMonths());
    }

    public function testAYearlyPatternTakesTheAnchorMonthFromTheCaller(): void
    {
        // Act
        $state = $this->resolve(
            RecurrenceMode::Weekday,
            RecurrencePeriod::Year,
            [RecurrenceOrdinal::Second],
            [Weekday::Sunday],
        );

        // Assert
        static::assertSame('FREQ=YEARLY;BYMONTH=8;BYDAY=2SU', $state->pattern(8)->toRfcString());
        static::assertSame('FREQ=MONTHLY;BYDAY=2SU', $state->pattern(period: RecurrencePeriod::Month)->toRfcString());
    }
}
