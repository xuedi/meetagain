<?php declare(strict_types=1);

namespace App\Enum;

enum RecurrencePeriod: string
{
    case Week = 'week';
    case TwoWeeks = 'two_weeks';
    case Month = 'month';
    case TwoMonths = 'two_months';
    case Quarter = 'quarter';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'admin_event.recurrence_period_week',
            self::TwoWeeks => 'admin_event.recurrence_period_two_weeks',
            self::Month => 'admin_event.recurrence_period_month',
            self::TwoMonths => 'admin_event.recurrence_period_two_months',
            self::Quarter => 'admin_event.recurrence_period_quarter',
            self::Year => 'admin_event.recurrence_period_year',
        };
    }

    public function frequency(): string
    {
        return match ($this) {
            self::Week, self::TwoWeeks => 'WEEKLY',
            self::Month, self::TwoMonths, self::Quarter => 'MONTHLY',
            self::Year => 'YEARLY',
        };
    }

    public function interval(): int
    {
        return match ($this) {
            self::TwoWeeks, self::TwoMonths => 2,
            self::Quarter => 3,
            default => 1,
        };
    }

    public function isWeekly(): bool
    {
        return 'WEEKLY' === $this->frequency();
    }

    /** Must span at least one full period or a sparse series never extends. */
    public function lookaheadModifier(): string
    {
        return match ($this) {
            self::Week, self::TwoWeeks => '+3 months',
            self::Month => '+6 months',
            self::TwoMonths, self::Quarter => '+12 months',
            self::Year => '+3 years',
        };
    }
}
