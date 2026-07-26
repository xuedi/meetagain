<?php declare(strict_types=1);

namespace App\Enum;

enum RecurrenceMode: string
{
    case Weekday = 'weekday';
    case DayOfMonth = 'day_of_month';
    case EveryDay = 'every_day';

    public function label(): string
    {
        return match ($this) {
            self::Weekday => 'admin_event.recurrence_mode_weekday',
            self::DayOfMonth => 'admin_event.recurrence_mode_day_of_month',
            self::EveryDay => 'admin_event.recurrence_mode_every_day',
        };
    }
}
