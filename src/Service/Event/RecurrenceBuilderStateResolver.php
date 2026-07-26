<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\ValueObject\RecurrenceBuilderState;
use App\ValueObject\RecurrencePattern;

final readonly class RecurrenceBuilderStateResolver
{
    private const int DEFAULT_DAY_OF_MONTH = 1;

    /**
     * @param list<RecurrenceOrdinal> $ordinals
     * @param list<Weekday>           $weekdays
     * @param list<int>               $daysOfMonth
     */
    public function resolve(
        RecurrenceMode $mode,
        RecurrencePeriod $period,
        array $ordinals,
        array $weekdays,
        array $daysOfMonth,
        Weekday $fallbackWeekday,
    ): RecurrenceBuilderState {
        // The builder never authors a daily rule; Daily stays a preset.
        $mode = RecurrenceMode::EveryDay === $mode ? RecurrenceMode::Weekday : $mode;
        $period = $period->carriesDayRule() ? $period : RecurrencePeriod::Month;
        if (RecurrenceMode::DayOfMonth === $mode && $period->isWeekly()) {
            $period = RecurrencePeriod::Month;
        }

        $weekdays = Weekday::sort($weekdays);
        $weekdays = [] === $weekdays ? [$fallbackWeekday] : $weekdays;

        if ($period->isWeekly()) {
            $ordinals = [];
        } else {
            $weekdays = [$weekdays[0]];
            $ordinals = RecurrenceOrdinal::sort($ordinals);
            $ordinals = [] === $ordinals ? [RecurrenceOrdinal::First] : $ordinals;
        }

        return new RecurrenceBuilderState(
            $mode,
            $period,
            $ordinals,
            $weekdays,
            $this->resolveDaysOfMonth($daysOfMonth),
            $this->selectablePeriods($mode),
        );
    }

    /**
     * @param list<int> $daysOfMonth
     *
     * @return list<int>
     */
    private function resolveDaysOfMonth(array $daysOfMonth): array
    {
        // "Last day" is its own sentence, so picking it alongside numbered days keeps only itself.
        if (in_array(RecurrencePattern::LAST_DAY_OF_MONTH, $daysOfMonth, true)) {
            return [RecurrencePattern::LAST_DAY_OF_MONTH];
        }

        $days = array_values(array_unique(array_filter(
            $daysOfMonth,
            static fn(int $day): bool => $day >= 1 && $day <= 31,
        )));
        sort($days);

        return [] === $days ? [self::DEFAULT_DAY_OF_MONTH] : $days;
    }

    /**
     * @return list<RecurrencePeriod>
     */
    private function selectablePeriods(RecurrenceMode $mode): array
    {
        return array_values(array_filter(
            RecurrencePeriod::cases(),
            static fn(RecurrencePeriod $case): bool => $case->carriesDayRule()
                && (RecurrenceMode::DayOfMonth !== $mode || !$case->isWeekly()),
        ));
    }
}
