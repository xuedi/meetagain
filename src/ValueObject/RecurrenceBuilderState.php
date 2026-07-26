<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;

/**
 * A builder selection that is guaranteed to produce a valid RecurrencePattern, plus which controls
 * that selection leaves applicable. Only RecurrenceBuilderStateResolver may construct one.
 */
final readonly class RecurrenceBuilderState
{
    private const int SHORT_MONTH_THRESHOLD = 29;

    /**
     * @param list<RecurrenceOrdinal> $ordinals
     * @param list<Weekday>           $weekdays
     * @param list<int>               $daysOfMonth
     * @param list<RecurrencePeriod>  $periods
     */
    public function __construct(
        public RecurrenceMode $mode,
        public RecurrencePeriod $period,
        public array $ordinals,
        public array $weekdays,
        public array $daysOfMonth,
        public array $periods,
    ) {}

    public function showsOrdinal(): bool
    {
        return RecurrenceMode::Weekday === $this->mode && !$this->period->isWeekly();
    }

    public function showsWeekday(): bool
    {
        return RecurrenceMode::Weekday === $this->mode;
    }

    public function showsDayOfMonth(): bool
    {
        return RecurrenceMode::DayOfMonth === $this->mode;
    }

    public function allowsSeveralWeekdays(): bool
    {
        return $this->period->isWeekly();
    }

    public function allowsSeveralEntries(): bool
    {
        return $this->showsOrdinal() || $this->allowsSeveralWeekdays() || $this->showsDayOfMonth();
    }

    public function warnsAboutShortMonths(): bool
    {
        return $this->showsDayOfMonth() && [] !== array_filter(
            $this->daysOfMonth,
            static fn(int $day): bool => $day >= self::SHORT_MONTH_THRESHOLD,
        );
    }

    public function pattern(?int $anchorMonth = null, ?RecurrencePeriod $period = null): RecurrencePattern
    {
        $period ??= $this->period;

        return RecurrenceMode::DayOfMonth === $this->mode
            ? RecurrencePattern::dayOfMonth($period, $this->daysOfMonth, $anchorMonth)
            : RecurrencePattern::weekday($period, $this->weekdays, $this->ordinals, $anchorMonth);
    }
}
