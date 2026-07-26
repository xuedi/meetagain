<?php declare(strict_types=1);

namespace App\Enum;

enum RecurrenceOrdinal: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;
    case Last = -1;

    public function label(): string
    {
        return match ($this) {
            self::First => 'admin_event.recurrence_ordinal_first',
            self::Second => 'admin_event.recurrence_ordinal_second',
            self::Third => 'admin_event.recurrence_ordinal_third',
            self::Fourth => 'admin_event.recurrence_ordinal_fourth',
            self::Last => 'admin_event.recurrence_ordinal_last',
        };
    }
}
