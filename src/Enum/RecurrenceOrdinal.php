<?php declare(strict_types=1);

namespace App\Enum;

enum RecurrenceOrdinal: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;
    case Last = -1;

    /**
     * Declaration order is calendar order (First..Fourth, Last); the backed values are not.
     *
     * @param list<self> $ordinals
     *
     * @return list<self>
     */
    public static function sort(array $ordinals): array
    {
        $order = self::cases();
        $unique = array_values(array_unique($ordinals, SORT_REGULAR));
        usort($unique, static fn(self $a, self $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        return $unique;
    }

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
